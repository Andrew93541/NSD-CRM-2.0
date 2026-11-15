<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\SalaryAdvance;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use App\Services\Workforce\PayrollCalculationService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollService
    ) {}

    protected function authorizePayrollView(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->isExplicitlyDenied('payroll.view')) {
            abort(403, 'You are not authorized to access the Payroll module.');
        }

        if (! $user->canPerform('payroll.view', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])
            && ! $user->hasPermission('payroll.create')
            && ! $user->hasPermission('payroll.edit')
            && ! $user->hasPermission('payroll.approve')
        ) {
            abort(403, 'You are not authorized to access the Payroll module.');
        }
    }

    protected function authorizePayrollPermission(string $permission, array $fallbackRoles): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->isExplicitlyDenied($permission) || $user->isExplicitlyDenied('payroll.view')) {
            abort(403, 'You are not authorized to perform this payroll action.');
        }

        if (! $user->canPerform($permission, $fallbackRoles)) {
            abort(403, 'You are not authorized to perform this payroll action.');
        }
    }

    protected function hasUnrestrictedPayrollSiteAccess(User $user): bool
    {
        if ($user->hasRole(['super_admin', 'company_admin'])) {
            return true;
        }

        if ($user->isRestrictedToAssignedSites()) {
            return false;
        }

        if ($user->hasRole(['site_manager', 'site_supervisor', 'supervisor', 'warehouse_manager'])) {
            return false;
        }

        return true;
    }

    protected function authorizePayrollPeriodScope(PayrollPeriod $payrollPeriod, User $user, ?PayrollEntry $entry = null): void
    {
        if ((int) $payrollPeriod->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        if ($entry !== null && (int) $entry->payroll_period_id !== (int) $payrollPeriod->id) {
            abort(403);
        }

        if ($payrollPeriod->site_id !== null) {
            if (! $this->hasUnrestrictedPayrollSiteAccess($user) && ! $user->isAssignedToSite((int) $payrollPeriod->site_id)) {
                abort(403, 'You are not authorized to access this site payroll period.');
            }
        } elseif (! $this->hasUnrestrictedPayrollSiteAccess($user)) {
            abort(403, 'Site-restricted users cannot access company-wide payroll periods or exports.');
        }

        if ($entry !== null && $entry->site_id !== null && ! $this->hasUnrestrictedPayrollSiteAccess($user) && ! $user->isAssignedToSite((int) $entry->site_id)) {
            abort(403, 'You are not authorized to access payroll entries for another site.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizePayrollView();

        /** @var User $user */
        $user = Auth::user();
        $hasAllSiteAccess = $this->hasUnrestrictedPayrollSiteAccess($user);
        $accessibleSiteIds = $hasAllSiteAccess ? null : $user->accessibleSiteIds();

        $periodsQuery = PayrollPeriod::with(['site', 'calculator', 'approver'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereNotNull('site_id')->whereIn('site_id', $accessibleSiteIds);
            })
            ->latest('start_date')
            ->latest('id');

        $periods = $periodsQuery->paginate(15);

        $advancesQuery = SalaryAdvance::with(['worker', 'site', 'approver'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereNotNull('site_id')->whereIn('site_id', $accessibleSiteIds);
            })
            ->latest('advance_date')
            ->latest('id');

        $recentAdvances = $advancesQuery->limit(25)->get();

        $sites = Site::where('status', 'active')
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('id', $accessibleSiteIds))
            ->orderBy('name')
            ->get();

        $workers = Worker::where('status', 'active')
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('assigned_site_id', $accessibleSiteIds))
            ->orderBy('first_name')
            ->get();

        return view('payroll.index', compact('periods', 'recentAdvances', 'sites', 'workers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePayrollPermission('payroll.create', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($validated['site_id'])) {
            $site = Site::findOrFail((int) $validated['site_id']);
            if (! $this->hasUnrestrictedPayrollSiteAccess($user) && ! $user->isAssignedToSite($site)) {
                abort(403, 'You are not authorized to generate payroll for this site.');
            }
        } elseif (! $this->hasUnrestrictedPayrollSiteAccess($user)) {
            abort(403, 'Site-restricted users must select an assigned site when generating a payroll period.');
        }

        $period = DB::transaction(function () use ($companyId, $validated, $user) {
            $periodCode = 'PAY-'.Carbon::parse($validated['start_date'])->format('Ym').'-'.strtoupper(Str::random(4));
            while (PayrollPeriod::withoutGlobalScopes()->where('company_id', $companyId)->where('period_code', $periodCode)->exists()) {
                $periodCode = 'PAY-'.Carbon::parse($validated['start_date'])->format('Ym').'-'.strtoupper(Str::random(4));
            }

            $period = PayrollPeriod::create([
                'company_id' => $companyId,
                'site_id' => $validated['site_id'] ?? null,
                'period_code' => $periodCode,
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->payrollService->calculatePeriod($period, $user);

            return $period;
        });

        return redirect()
            ->route('payroll.show', $period)
            ->with('success', "Payroll period {$period->period_code} created and calculated across all compensation categories.");
    }

    public function show(PayrollPeriod $payrollPeriod): View
    {
        $this->authorizePayrollView();

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user);

        $payrollPeriod->load(['site', 'calculator', 'approver', 'entries.worker.subcontractor', 'entries.site']);

        return view('payroll.show', [
            'period' => $payrollPeriod,
            'entries' => $payrollPeriod->entries,
        ]);
    }

    public function recalculate(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorizePayrollPermission('payroll.edit', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']);

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user);

        if ($payrollPeriod->isLocked()) {
            return back()->withErrors(['status' => 'Cannot recalculate a locked payroll period.']);
        }

        $this->payrollService->calculatePeriod($payrollPeriod, $user);

        return back()->with('success', "Payroll period {$payrollPeriod->period_code} recalculated from latest attendance and salary advances.");
    }

    public function adjustEntry(Request $request, PayrollPeriod $payrollPeriod, PayrollEntry $entry): RedirectResponse
    {
        $this->authorizePayrollPermission('payroll.edit', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']);

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user, $entry);

        if ($payrollPeriod->isLocked()) {
            return back()->withErrors(['status' => 'Cannot adjust entries on a locked payroll period.']);
        }

        $validated = $request->validate([
            'bonus_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'other_deduction' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'deduction_notes' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'in:pending,paid'],
        ]);

        $bonus = round((float) ($validated['bonus_amount'] ?? $entry->bonus_amount), 2);
        $otherDed = round((float) ($validated['other_deduction'] ?? $entry->other_deduction), 2);
        $gross = round((float) $entry->base_pay + (float) $entry->overtime_pay + $bonus, 2);

        $entry->loadMissing('worker');
        $hasStatutoryColumns = Schema::hasColumn('payroll_entries', 'pf_employee_amount');
        $statutory = $entry->worker
            ? $this->payrollService->evaluateStatutoryDeductions(
                $entry->worker,
                $gross,
                Carbon::parse($payrollPeriod->end_date)->endOfDay()
            )
            : [
                'pf_employee_amount' => (float) ($entry->pf_employee_amount ?? 0),
                'pf_employer_amount' => (float) ($entry->pf_employer_amount ?? 0),
                'esic_employee_amount' => (float) ($entry->esic_employee_amount ?? 0),
                'esic_employer_amount' => (float) ($entry->esic_employer_amount ?? 0),
                'pt_amount' => (float) ($entry->pt_amount ?? 0),
                'statutory_employee_total' => $entry->statutoryEmployeeDeductionTotal(),
                'statutory_config_state' => $entry->statutory_config_state ?? 'unconfigured',
                'statutory_snapshot' => $entry->statutory_snapshot,
            ];

        $availableAfterStatutory = max(0.0, round($gross - $statutory['statutory_employee_total'], 2));
        $advanceDed = min($availableAfterStatutory, round((float) $entry->advance_deduction, 2));
        $maxAllowedOtherDed = max(0.0, round($availableAfterStatutory - $advanceDed, 2));

        if ($otherDed > $maxAllowedOtherDed + 0.0001) {
            throw ValidationException::withMessages([
                'other_deduction' => 'Manual deduction (₹'.number_format($otherDed, 2).') cannot exceed remaining payable earnings after statutory and advance deductions (₹'.number_format($maxAllowedOtherDed, 2).').',
            ]);
        }

        $net = max(0.0, round($gross - $statutory['statutory_employee_total'] - $advanceDed - $otherDed, 2));

        $oldValues = [
            'bonus_amount' => (float) $entry->bonus_amount,
            'advance_deduction' => (float) $entry->advance_deduction,
            'other_deduction' => (float) $entry->other_deduction,
            'net_pay' => (float) $entry->net_pay,
        ];

        DB::transaction(function () use ($entry, $payrollPeriod, $validated, $bonus, $gross, $advanceDed, $otherDed, $net, $hasStatutoryColumns, $statutory, $user, $oldValues) {
            $updatePayload = [
                'bonus_amount' => $bonus,
                'gross_pay' => $gross,
                'advance_deduction' => $advanceDed,
                'other_deduction' => $otherDed,
                'deduction_notes' => $validated['deduction_notes'] ?? $entry->deduction_notes,
                'net_pay' => $net,
                'payment_status' => $validated['payment_status'] ?? $entry->payment_status,
                'paid_at' => ($validated['payment_status'] ?? $entry->payment_status) === 'paid' ? ($entry->paid_at ?? now()) : null,
            ];

            if ($hasStatutoryColumns) {
                $updatePayload['pf_employee_amount'] = $statutory['pf_employee_amount'];
                $updatePayload['pf_employer_amount'] = $statutory['pf_employer_amount'];
                $updatePayload['esic_employee_amount'] = $statutory['esic_employee_amount'];
                $updatePayload['esic_employer_amount'] = $statutory['esic_employer_amount'];
                $updatePayload['pt_amount'] = $statutory['pt_amount'];
                $updatePayload['statutory_config_state'] = $statutory['statutory_config_state'];
                $updatePayload['statutory_snapshot'] = $statutory['statutory_snapshot'];
            }

            $entry->update($updatePayload);

            // Refresh period totals and record latest preparer for maker-checker separation
            $allEntries = PayrollEntry::where('payroll_period_id', $payrollPeriod->id)->get();
            $payrollPeriod->update([
                'total_gross_amount' => round((float) $allEntries->sum('gross_pay'), 2),
                'total_deductions' => round((float) $allEntries->sum(fn (PayrollEntry $e) => $e->totalEmployeeDeductions()), 2),
                'total_net_amount' => round((float) $allEntries->sum('net_pay'), 2),
                'calculated_by' => $user->id,
            ]);

            AuditLog::record(
                'payroll.entry.adjusted',
                $entry,
                $oldValues,
                [
                    'bonus_amount' => $bonus,
                    'advance_deduction' => $advanceDed,
                    'other_deduction' => $otherDed,
                    'net_pay' => $net,
                ],
                "Adjusted payroll entry for {$entry->worker?->full_name} ({$payrollPeriod->period_code})"
            );
        });

        return back()->with('success', "Updated payroll entry for {$entry->worker?->full_name}.");
    }

    public function approveAndLock(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorizePayrollPermission('payroll.approve', ['super_admin', 'company_admin', 'senior_manager', 'site_manager']);

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user);

        if ($payrollPeriod->isLocked()) {
            return back()->with('info', 'This payroll period is already locked.');
        }

        if ($payrollPeriod->calculated_by !== null && (int) $payrollPeriod->calculated_by === (int) $user->id) {
            abort(403, 'Maker-checker separation of duties: The user who prepared or last modified this payroll period cannot approve and lock it.');
        }

        $this->payrollService->approveAndLockPeriod($payrollPeriod, $user);

        return back()->with('success', "Payroll period {$payrollPeriod->period_code} approved & locked. Attendance records and advance deductions have been finalized.");
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $this->authorizePayrollPermission('payroll.create', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $worker = Worker::findOrFail((int) $validated['worker_id']);
        if ((int) $worker->company_id !== $companyId) {
            abort(403);
        }

        if ($worker->assigned_site_id && ! $user->isAssignedToSite((int) $worker->assigned_site_id)) {
            abort(403, 'You are not authorized to record an advance for a worker at another site.');
        }

        $advance = SalaryAdvance::create([
            'company_id' => $companyId,
            'worker_id' => $worker->id,
            'site_id' => $worker->assigned_site_id,
            'advance_date' => $validated['advance_date'],
            'amount' => round((float) $validated['amount'], 2),
            'deducted_amount' => 0.00,
            'status' => 'approved',
            'reason' => $validated['reason'] ?? 'Site Salary Advance',
            'approved_by' => $user->id,
        ]);

        AuditLog::record(
            'payroll.advance.recorded',
            $advance,
            null,
            ['worker_id' => $worker->id, 'amount' => $advance->amount]
        );

        return back()->with('success', 'Salary advance of ₹'.number_format((float) $advance->amount, 2)." recorded for {$worker->full_name} ({$worker->code}).");
    }

    public function payslip(PayrollPeriod $payrollPeriod, PayrollEntry $entry): View
    {
        $this->authorizePayrollView();

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user, $entry);

        $entry->load(['worker.subcontractor', 'worker.assignedSite', 'site', 'company']);
        $payrollPeriod->load(['company', 'site', 'approver']);

        return view('payroll.payslip', [
            'period' => $payrollPeriod,
            'entry' => $entry,
            'worker' => $entry->worker,
            'company' => $payrollPeriod->company,
        ]);
    }

    public function payslipPdf(PayrollPeriod $payrollPeriod, PayrollEntry $entry): StreamedResponse
    {
        $this->authorizePayrollView();

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user, $entry);

        $entry->load(['worker.subcontractor', 'worker.assignedSite', 'site', 'company']);
        $payrollPeriod->load(['company', 'site', 'approver']);

        $html = view('payroll.pdf.payslip', [
            'period' => $payrollPeriod,
            'entry' => $entry,
            'worker' => $entry->worker,
            'company' => $payrollPeriod->company,
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfBytes = $dompdf->output();

        AuditLog::record(
            'payroll.payslip.pdf_exported',
            $entry,
            null,
            ['period_code' => $payrollPeriod->period_code, 'worker_id' => $entry->worker_id],
            "Exported PDF payslip for {$entry->worker?->full_name} ({$payrollPeriod->period_code})"
        );

        $workerCode = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) ($entry->worker?->code ?? $entry->id));
        $periodCode = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) $payrollPeriod->period_code);

        return response()->streamDownload(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, "payslip_{$periodCode}_{$workerCode}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function exportBankPayoutCsv(PayrollPeriod $payrollPeriod): StreamedResponse
    {
        $this->authorizePayrollPermission('payroll.view', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']);

        /** @var User $user */
        $user = Auth::user();
        $this->authorizePayrollPeriodScope($payrollPeriod, $user);

        $hasAllSiteAccess = $this->hasUnrestrictedPayrollSiteAccess($user);
        $entries = PayrollEntry::with(['worker', 'site'])
            ->where('company_id', $payrollPeriod->company_id)
            ->where('payroll_period_id', $payrollPeriod->id)
            ->when(! $hasAllSiteAccess, fn ($q) => $q->whereIn('site_id', $user->accessibleSiteIds()))
            ->orderBy('id')
            ->get();

        AuditLog::record(
            'payroll.payout_csv.exported',
            $payrollPeriod,
            null,
            ['period_code' => $payrollPeriod->period_code, 'entries_count' => $entries->count()],
            "Exported bank NEFT/UPI payout schedule for {$payrollPeriod->period_code}"
        );

        $periodCode = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) $payrollPeriod->period_code);
        $filename = "bank_payout_{$periodCode}.csv";

        return response()->streamDownload(function () use ($payrollPeriod, $entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Period Code',
                'Worker Code',
                'Worker Name',
                'Worker Type',
                'Beneficiary Account Name',
                'Bank Account Number',
                'Bank IFSC',
                'UPI ID',
                'Gross Pay (INR)',
                'Employee Deductions (INR)',
                'Net Payable (INR)',
                'Payment Status',
            ]);

            foreach ($entries as $entry) {
                $worker = $entry->worker;
                $beneficiary = $worker?->bank_account_name ?: $worker?->full_name ?: 'Unknown';
                $statutoryEmployeeDed = round(
                    (float) ($entry->pf_employee_amount ?? 0)
                    + (float) ($entry->esic_employee_amount ?? 0)
                    + (float) ($entry->pt_amount ?? 0),
                    2
                );
                $totalEmployeeDed = round(
                    (float) $entry->advance_deduction
                    + (float) $entry->other_deduction
                    + $statutoryEmployeeDed,
                    2
                );

                fputcsv($out, [
                    $this->sanitizeCsvCell((string) $payrollPeriod->period_code),
                    $this->sanitizeCsvCell((string) ($worker?->code ?? '')),
                    $this->sanitizeCsvCell((string) ($worker?->full_name ?? '')),
                    $this->sanitizeCsvCell((string) ($worker?->worker_type ?? '')),
                    $this->sanitizeCsvCell((string) $beneficiary),
                    $this->sanitizeCsvCell((string) ($worker?->bank_account_number ?? '')),
                    $this->sanitizeCsvCell((string) ($worker?->bank_ifsc ?? '')),
                    $this->sanitizeCsvCell((string) ($worker?->upi_id ?? '')),
                    number_format((float) $entry->gross_pay, 2, '.', ''),
                    number_format($totalEmployeeDed, 2, '.', ''),
                    number_format((float) $entry->net_pay, 2, '.', ''),
                    $this->sanitizeCsvCell((string) $entry->payment_status),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
