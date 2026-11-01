<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\SalaryAdvance;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use App\Services\Workforce\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollService
    ) {}

    protected function authorizePayrollAccess(bool $requireWrite = false): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        if ($requireWrite) {
            if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])) {
                abort(403, 'You are not authorized to modify payroll records.');
            }
            return;
        }

        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'])) {
            abort(403, 'You are not authorized to access the Payroll module.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizePayrollAccess(false);

        $user = Auth::user();
        $accessibleSiteIds = $user->accessibleSiteIds();

        $periodsQuery = PayrollPeriod::with(['site', 'calculator', 'approver'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereIn('site_id', $accessibleSiteIds);
            })
            ->latest('start_date')
            ->latest('id');

        $periods = $periodsQuery->paginate(15);

        $advancesQuery = SalaryAdvance::with(['worker', 'site', 'approver'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereIn('site_id', $accessibleSiteIds);
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
        $this->authorizePayrollAccess(true);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!empty($validated['site_id'])) {
            $site = Site::findOrFail((int) $validated['site_id']);
            if (!$user->isAssignedToSite($site)) {
                abort(403, 'You are not authorized to generate payroll for this site.');
            }
        } elseif (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
            abort(403, 'Site managers must select an assigned site when generating a payroll period.');
        }

        $periodCode = 'PAY-' . Carbon::parse($validated['start_date'])->format('Ym') . '-' . strtoupper(Str::random(4));

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

        return redirect()
            ->route('payroll.show', $period)
            ->with('success', "Payroll period {$period->period_code} created and calculated across all compensation categories.");
    }

    public function show(PayrollPeriod $payrollPeriod): View
    {
        $this->authorizePayrollAccess(false);

        $user = Auth::user();
        if ((int) $payrollPeriod->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        if ($payrollPeriod->site_id && !$user->isAssignedToSite((int) $payrollPeriod->site_id)) {
            abort(403, 'You are not authorized to view this site payroll period.');
        }

        $payrollPeriod->load(['site', 'calculator', 'approver', 'entries.worker.subcontractor', 'entries.site']);

        return view('payroll.show', [
            'period' => $payrollPeriod,
            'entries' => $payrollPeriod->entries,
        ]);
    }

    public function recalculate(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorizePayrollAccess(true);

        $user = Auth::user();
        if ((int) $payrollPeriod->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        if ($payrollPeriod->site_id && !$user->isAssignedToSite((int) $payrollPeriod->site_id)) {
            abort(403);
        }

        if ($payrollPeriod->isLocked()) {
            return back()->withErrors(['status' => 'Cannot recalculate a locked payroll period.']);
        }

        $this->payrollService->calculatePeriod($payrollPeriod, $user);

        return back()->with('success', "Payroll period {$payrollPeriod->period_code} recalculated from latest attendance and salary advances.");
    }

    public function adjustEntry(Request $request, PayrollPeriod $payrollPeriod, PayrollEntry $entry): RedirectResponse
    {
        $this->authorizePayrollAccess(true);

        $user = Auth::user();
        if ((int) $payrollPeriod->company_id !== (int) TenantContext::getCompanyId()
            || (int) $entry->payroll_period_id !== (int) $payrollPeriod->id
        ) {
            abort(403);
        }

        if ($payrollPeriod->site_id && !$user->isAssignedToSite((int) $payrollPeriod->site_id)) {
            abort(403);
        }

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
        $net = max(0.0, round($gross - (float) $entry->advance_deduction - $otherDed, 2));

        $entry->update([
            'bonus_amount' => $bonus,
            'gross_pay' => $gross,
            'other_deduction' => $otherDed,
            'deduction_notes' => $validated['deduction_notes'] ?? $entry->deduction_notes,
            'net_pay' => $net,
            'payment_status' => $validated['payment_status'] ?? $entry->payment_status,
            'paid_at' => ($validated['payment_status'] ?? $entry->payment_status) === 'paid' ? ($entry->paid_at ?? now()) : null,
        ]);

        // Refresh period totals
        $allEntries = PayrollEntry::where('payroll_period_id', $payrollPeriod->id)->get();
        $payrollPeriod->update([
            'total_gross_amount' => round((float) $allEntries->sum('gross_pay'), 2),
            'total_deductions' => round((float) $allEntries->sum(fn ($e) => (float) $e->advance_deduction + (float) $e->other_deduction), 2),
            'total_net_amount' => round((float) $allEntries->sum('net_pay'), 2),
        ]);

        return back()->with('success', "Updated payroll entry for {$entry->worker?->full_name}.");
    }

    public function approveAndLock(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->authorizePayrollAccess(true);

        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Only management roles can approve and lock payroll periods.');
        }

        if ((int) $payrollPeriod->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        if ($payrollPeriod->site_id && !$user->isAssignedToSite((int) $payrollPeriod->site_id)) {
            abort(403);
        }

        if ($payrollPeriod->isLocked()) {
            return back()->with('info', 'This payroll period is already locked.');
        }

        $this->payrollService->approveAndLockPeriod($payrollPeriod, $user);

        return back()->with('success', "Payroll period {$payrollPeriod->period_code} approved & locked. Attendance records and advance deductions have been finalized.");
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $this->authorizePayrollAccess(true);

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

        if ($worker->assigned_site_id && !$user->isAssignedToSite((int) $worker->assigned_site_id)) {
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

        return back()->with('success', "Salary advance of ₹" . number_format((float) $advance->amount, 2) . " recorded for {$worker->full_name} ({$worker->code}).");
    }

    public function payslip(PayrollPeriod $payrollPeriod, PayrollEntry $entry): View
    {
        $this->authorizePayrollAccess(false);

        $user = Auth::user();
        if ((int) $payrollPeriod->company_id !== (int) TenantContext::getCompanyId()
            || (int) $entry->payroll_period_id !== (int) $payrollPeriod->id
        ) {
            abort(403);
        }

        if ($payrollPeriod->site_id && !$user->isAssignedToSite((int) $payrollPeriod->site_id)) {
            abort(403);
        }

        $entry->load(['worker.subcontractor', 'worker.assignedSite', 'site', 'company']);
        $payrollPeriod->load(['company', 'site', 'approver']);

        return view('payroll.payslip', [
            'period' => $payrollPeriod,
            'entry' => $entry,
            'worker' => $entry->worker,
            'company' => $payrollPeriod->company,
        ]);
    }
}
