<?php

namespace App\Services\Workforce;

use App\Models\AuditLog;
use App\Models\DailyAttendance;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayrollCalculationService
{
    /**
     * Evaluate individual employee/worker statutory deductions (PF, ESIC, PT) without any
     * hardcoded company-wide rate or ceiling assumptions.
     */
    public function evaluateStatutoryDeductions(Worker $worker, float $grossPay, Carbon $periodEndDate): array
    {
        $pfStatus = in_array($worker->pf_status, ['unconfigured', 'applicable', 'not_applicable'], true)
            ? $worker->pf_status
            : 'unconfigured';
        $esicStatus = in_array($worker->esic_status, ['unconfigured', 'applicable', 'not_applicable'], true)
            ? $worker->esic_status
            : 'unconfigured';
        $ptStatus = in_array($worker->pt_status, ['unconfigured', 'applicable', 'not_applicable'], true)
            ? $worker->pt_status
            : 'unconfigured';

        $hasUnconfigured = in_array('unconfigured', [$pfStatus, $esicStatus, $ptStatus], true);
        $hasApplicable = in_array('applicable', [$pfStatus, $esicStatus, $ptStatus], true);

        $effectiveFrom = $worker->statutory_effective_from
            ? Carbon::parse($worker->statutory_effective_from)->startOfDay()
            : null;
        $isBeforeEffectiveDate = $effectiveFrom !== null
            && $effectiveFrom->gt($periodEndDate->copy()->endOfDay());

        $pfEmployeeAmount = 0.00;
        $pfEmployerAmount = 0.00;
        $pfWageBase = 0.00;
        $pfEmpRate = $worker->pf_employee_rate !== null ? round(max(0.0, (float) $worker->pf_employee_rate), 2) : null;
        $pfEmprRate = $worker->pf_employer_rate !== null ? round(max(0.0, (float) $worker->pf_employer_rate), 2) : null;
        $pfCeiling = ($worker->pf_wage_ceiling !== null && (float) $worker->pf_wage_ceiling > 0)
            ? round((float) $worker->pf_wage_ceiling, 2)
            : null;

        $esicEmployeeAmount = 0.00;
        $esicEmployerAmount = 0.00;
        $esicWageBase = 0.00;
        $esicEmpRate = $worker->esic_employee_rate !== null ? round(max(0.0, (float) $worker->esic_employee_rate), 2) : null;
        $esicEmprRate = $worker->esic_employer_rate !== null ? round(max(0.0, (float) $worker->esic_employer_rate), 2) : null;
        $esicCeiling = ($worker->esic_wage_ceiling !== null && (float) $worker->esic_wage_ceiling > 0)
            ? round((float) $worker->esic_wage_ceiling, 2)
            : null;

        $ptAmount = 0.00;
        $ptShortfall = 0.00;
        $ptConfiguredAmount = $worker->pt_monthly_amount !== null ? round(max(0.0, (float) $worker->pt_monthly_amount), 2) : null;

        if ($isBeforeEffectiveDate && $hasApplicable) {
            $pfReason = $pfStatus === 'applicable' ? 'not_yet_effective' : $pfStatus;
            $esicReason = $esicStatus === 'applicable' ? 'not_yet_effective' : $esicStatus;
            $ptReason = $ptStatus === 'applicable' ? 'not_yet_effective' : $ptStatus;
            $configState = $hasUnconfigured ? 'unconfigured' : 'not_yet_effective';
        } else {
            // 1. Provident Fund (PF)
            if ($pfStatus === 'applicable' && $grossPay > 0) {
                $pfWageBase = $pfCeiling !== null ? min($grossPay, $pfCeiling) : $grossPay;
                $pfEmployeeAmount = round($pfWageBase * (($pfEmpRate ?? 0.0) / 100.0), 2);
                $pfEmployerAmount = round($pfWageBase * (($pfEmprRate ?? 0.0) / 100.0), 2);
                $pfReason = 'calculated';
            } else {
                $pfReason = $pfStatus === 'applicable' ? 'zero_gross_pay' : $pfStatus;
            }

            // 2. ESIC
            if ($esicStatus === 'applicable' && $grossPay > 0) {
                if ($esicCeiling !== null && $grossPay > $esicCeiling) {
                    $esicReason = 'exceeds_wage_ceiling';
                } else {
                    $esicWageBase = $grossPay;
                    $esicEmployeeAmount = round($esicWageBase * (($esicEmpRate ?? 0.0) / 100.0), 2);
                    $esicEmployerAmount = round($esicWageBase * (($esicEmprRate ?? 0.0) / 100.0), 2);
                    $esicReason = 'calculated';
                }
            } else {
                $esicReason = $esicStatus === 'applicable' ? 'zero_gross_pay' : $esicStatus;
            }

            // 3. Professional Tax (PT) — capped to remaining gross pay after PF/ESIC so net pay never goes negative
            if ($ptStatus === 'applicable' && $grossPay > 0) {
                $desiredPt = $ptConfiguredAmount ?? 0.00;
                $availableForPt = max(0.0, round($grossPay - $pfEmployeeAmount - $esicEmployeeAmount, 2));
                if ($desiredPt > $availableForPt) {
                    $ptAmount = $availableForPt;
                    $ptShortfall = round($desiredPt - $ptAmount, 2);
                    $ptReason = 'capped_to_available_gross';
                } else {
                    $ptAmount = $desiredPt;
                    $ptReason = 'calculated';
                }
            } else {
                $ptReason = $ptStatus === 'applicable' ? 'zero_gross_pay' : $ptStatus;
            }

            $configState = $hasUnconfigured
                ? 'unconfigured'
                : ($hasApplicable ? 'applicable' : 'not_applicable');
        }

        $statutoryEmployeeTotal = round($pfEmployeeAmount + $esicEmployeeAmount + $ptAmount, 2);
        $statutoryEmployerTotal = round($pfEmployerAmount + $esicEmployerAmount, 2);

        $snapshot = [
            'config_state' => $configState,
            'effective_from' => $effectiveFrom?->toDateString(),
            'is_before_effective_date' => $isBeforeEffectiveDate,
            'evaluated_at' => now()->toIso8601String(),
            'pf' => [
                'status' => $pfStatus,
                'reason' => $pfReason,
                'employee_rate' => $pfEmpRate,
                'employer_rate' => $pfEmprRate,
                'wage_ceiling' => $pfCeiling,
                'wage_base' => $pfWageBase,
                'employee_amount' => $pfEmployeeAmount,
                'employer_amount' => $pfEmployerAmount,
                'uan_number' => $worker->uan_number,
            ],
            'esic' => [
                'status' => $esicStatus,
                'reason' => $esicReason,
                'employee_rate' => $esicEmpRate,
                'employer_rate' => $esicEmprRate,
                'wage_ceiling' => $esicCeiling,
                'wage_base' => $esicWageBase,
                'employee_amount' => $esicEmployeeAmount,
                'employer_amount' => $esicEmployerAmount,
                'ip_number' => $worker->esic_ip_number,
            ],
            'pt' => [
                'status' => $ptStatus,
                'reason' => $ptReason,
                'configured_monthly_amount' => $ptConfiguredAmount,
                'amount' => $ptAmount,
                'unrecovered_shortfall' => $ptShortfall,
            ],
        ];

        return [
            'pf_employee_amount' => $pfEmployeeAmount,
            'pf_employer_amount' => $pfEmployerAmount,
            'esic_employee_amount' => $esicEmployeeAmount,
            'esic_employer_amount' => $esicEmployerAmount,
            'pt_amount' => $ptAmount,
            'statutory_employee_total' => $statutoryEmployeeTotal,
            'statutory_employer_total' => $statutoryEmployerTotal,
            'statutory_config_state' => $configState,
            'statutory_snapshot' => $snapshot,
        ];
    }

    /**
     * Calculate raw compensation figures for a worker given their attendance records and period dates.
     */
    public function calculateWorkerMetrics(
        Worker $worker,
        Collection $attendances,
        Carbon $startDate,
        Carbon $endDate,
        float $existingBonus = 0.00,
        float $existingOtherDeduction = 0.00,
        ?float $advanceDeductionOverride = null
    ): array {
        $presentDays = (float) $attendances->whereIn('status', ['present', 'late'])->count();
        $halfDays = (float) $attendances->where('status', 'half_day')->count();
        $paidLeaveDays = (float) $attendances->whereIn('status', ['leave', 'paid_leave'])->count();
        $holidayDays = (float) $attendances->whereIn('status', ['holiday', 'week_off'])->count();

        $effectiveDays = round($presentDays + ($halfDays * 0.5) + $paidLeaveDays + $holidayDays, 2);
        $workedHours = round((float) $attendances->sum('worked_hours'), 2);
        $overtimeHours = round((float) $attendances->sum('overtime_hours'), 2);

        $payType = $worker->pay_type ?: 'daily';
        $overtimeRate = round((float) ($worker->overtime_rate ?? 0), 2);
        $periodCalendarDays = max(1, (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1);

        switch ($payType) {
            case 'monthly':
                $baseRate = round((float) ($worker->monthly_salary > 0 ? $worker->monthly_salary : ((float) $worker->daily_rate * 30)), 2);
                $perDayRate = $baseRate / $periodCalendarDays;
                $basePay = round($perDayRate * $effectiveDays, 2);
                break;

            case 'hourly':
                $baseRate = round((float) ($worker->hourly_rate > 0 ? $worker->hourly_rate : ((float) $worker->daily_rate / 8)), 2);
                $regularHours = max(0.0, $workedHours - $overtimeHours);
                if ($regularHours <= 0 && $effectiveDays > 0) {
                    $regularHours = $effectiveDays * 8.0;
                }
                $basePay = round($regularHours * $baseRate, 2);
                break;

            case 'contract':
                $baseRate = round((float) ($worker->contract_rate > 0 ? $worker->contract_rate : (float) $worker->daily_rate), 2);
                $basePay = ($effectiveDays > 0 || $workedHours > 0) ? $baseRate : 0.00;
                break;

            case 'daily':
            default:
                $payType = 'daily';
                $baseRate = round((float) ($worker->daily_rate ?? 0), 2);
                $basePay = round($effectiveDays * $baseRate, 2);
                break;
        }

        $overtimePay = round($overtimeHours * $overtimeRate, 2);
        $bonusAmount = round(max(0.0, $existingBonus), 2);
        $grossPay = round($basePay + $overtimePay + $bonusAmount, 2);

        $statutory = $this->evaluateStatutoryDeductions($worker, $grossPay, $endDate);
        $statutoryEmployeeTotal = $statutory['statutory_employee_total'];

        $availableAfterStatutory = max(0.0, round($grossPay - $statutoryEmployeeTotal, 2));

        if ($advanceDeductionOverride !== null) {
            $advanceDeduction = min($availableAfterStatutory, round(max(0.0, $advanceDeductionOverride), 2));
        } else {
            $pendingAdvances = SalaryAdvance::withoutGlobalScopes()
                ->where('company_id', $worker->company_id)
                ->where('worker_id', $worker->id)
                ->where('status', 'approved')
                ->whereDate('advance_date', '<=', $endDate->toDateString())
                ->get();

            $outstandingAdvance = round((float) $pendingAdvances->sum(fn (SalaryAdvance $adv) => $adv->remainingBalance()), 2);
            $advanceDeduction = min($availableAfterStatutory, $outstandingAdvance);
        }

        $availableForOtherDeduction = max(0.0, round($availableAfterStatutory - $advanceDeduction, 2));
        $otherDeduction = min($availableForOtherDeduction, round(max(0.0, $existingOtherDeduction), 2));
        $netPay = max(0.0, round($grossPay - $statutoryEmployeeTotal - $advanceDeduction - $otherDeduction, 2));

        return [
            'pay_type' => $payType,
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'paid_leave_days' => $paidLeaveDays,
            'holiday_days' => $holidayDays,
            'effective_days' => $effectiveDays,
            'worked_hours' => $workedHours,
            'overtime_hours' => $overtimeHours,
            'base_rate' => $baseRate,
            'overtime_rate' => $overtimeRate,
            'base_pay' => $basePay,
            'overtime_pay' => $overtimePay,
            'bonus_amount' => $bonusAmount,
            'gross_pay' => $grossPay,
            'advance_deduction' => $advanceDeduction,
            'other_deduction' => $otherDeduction,
            'pf_employee_amount' => $statutory['pf_employee_amount'],
            'pf_employer_amount' => $statutory['pf_employer_amount'],
            'esic_employee_amount' => $statutory['esic_employee_amount'],
            'esic_employer_amount' => $statutory['esic_employer_amount'],
            'pt_amount' => $statutory['pt_amount'],
            'statutory_config_state' => $statutory['statutory_config_state'],
            'statutory_snapshot' => $statutory['statutory_snapshot'],
            'net_pay' => $netPay,
        ];
    }

    /**
     * Calculate or recalculate all entries for a PayrollPeriod.
     */
    public function calculatePeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        if ($period->isLocked()) {
            throw new RuntimeException('Cannot recalculate a locked payroll period.');
        }

        return DB::transaction(function () use ($period, $actor) {
            $startDate = Carbon::parse($period->start_date)->startOfDay();
            $endDate = Carbon::parse($period->end_date)->endOfDay();
            $hasStatutoryColumns = Schema::hasColumn('payroll_entries', 'pf_employee_amount');

            $workersQuery = Worker::withoutGlobalScopes()
                ->where('company_id', $period->company_id)
                ->where(function ($q) use ($startDate) {
                    $q->where('status', 'active')
                        ->orWhereDate('exit_date', '>=', $startDate->toDateString());
                });

            if ($period->site_id) {
                $workersQuery->where('assigned_site_id', $period->site_id);
            }

            $workers = $workersQuery->orderBy('first_name')->get();

            $attendancesQuery = DailyAttendance::withoutGlobalScopes()
                ->where('company_id', $period->company_id)
                ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()]);

            if ($period->site_id) {
                $attendancesQuery->where('site_id', $period->site_id);
            }

            $attendancesByWorker = $attendancesQuery->get()->groupBy('worker_id');

            $totalWorkers = 0;
            $totalGross = 0.00;
            $totalDeductions = 0.00;
            $totalNet = 0.00;

            foreach ($workers as $worker) {
                $existingEntry = PayrollEntry::withoutGlobalScopes()
                    ->where('payroll_period_id', $period->id)
                    ->where('worker_id', $worker->id)
                    ->first();

                $workerAttendances = $attendancesByWorker->get($worker->id, collect());

                $metrics = $this->calculateWorkerMetrics(
                    $worker,
                    $workerAttendances,
                    $startDate,
                    $endDate,
                    (float) ($existingEntry?->bonus_amount ?? 0.00),
                    (float) ($existingEntry?->other_deduction ?? 0.00)
                );

                $entryAttributes = [
                    'company_id' => $period->company_id,
                    'site_id' => $period->site_id ?: $worker->assigned_site_id,
                    'pay_type' => $metrics['pay_type'],
                    'present_days' => $metrics['present_days'],
                    'half_days' => $metrics['half_days'],
                    'paid_leave_days' => $metrics['paid_leave_days'],
                    'holiday_days' => $metrics['holiday_days'],
                    'effective_days' => $metrics['effective_days'],
                    'worked_hours' => $metrics['worked_hours'],
                    'overtime_hours' => $metrics['overtime_hours'],
                    'base_rate' => $metrics['base_rate'],
                    'overtime_rate' => $metrics['overtime_rate'],
                    'base_pay' => $metrics['base_pay'],
                    'overtime_pay' => $metrics['overtime_pay'],
                    'bonus_amount' => $metrics['bonus_amount'],
                    'gross_pay' => $metrics['gross_pay'],
                    'advance_deduction' => $metrics['advance_deduction'],
                    'other_deduction' => $metrics['other_deduction'],
                    'deduction_notes' => $existingEntry?->deduction_notes,
                    'net_pay' => $metrics['net_pay'],
                    'payment_status' => $existingEntry?->payment_status ?? 'pending',
                ];

                if ($hasStatutoryColumns) {
                    $entryAttributes['pf_employee_amount'] = $metrics['pf_employee_amount'];
                    $entryAttributes['pf_employer_amount'] = $metrics['pf_employer_amount'];
                    $entryAttributes['esic_employee_amount'] = $metrics['esic_employee_amount'];
                    $entryAttributes['esic_employer_amount'] = $metrics['esic_employer_amount'];
                    $entryAttributes['pt_amount'] = $metrics['pt_amount'];
                    $entryAttributes['statutory_config_state'] = $metrics['statutory_config_state'];
                    $entryAttributes['statutory_snapshot'] = $metrics['statutory_snapshot'];
                }

                PayrollEntry::withoutGlobalScopes()->updateOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'worker_id' => $worker->id,
                    ],
                    $entryAttributes
                );

                $statutoryDed = $metrics['pf_employee_amount'] + $metrics['esic_employee_amount'] + $metrics['pt_amount'];

                $totalWorkers++;
                $totalGross += $metrics['gross_pay'];
                $totalDeductions += ($metrics['advance_deduction'] + $metrics['other_deduction'] + $statutoryDed);
                $totalNet += $metrics['net_pay'];
            }

            $period->update([
                'status' => $period->isLocked() ? $period->status : 'calculated',
                'total_workers' => $totalWorkers,
                'total_gross_amount' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net_amount' => round($totalNet, 2),
                'calculated_by' => $actor->id,
            ]);

            AuditLog::record(
                'payroll.period.calculated',
                $period,
                null,
                [
                    'period_code' => $period->period_code,
                    'total_workers' => $totalWorkers,
                    'total_net_amount' => round($totalNet, 2),
                ]
            );

            return $period->fresh(['entries.worker', 'site']);
        });
    }

    /**
     * Approve & Lock a PayrollPeriod, enforcing maker-checker separation and statutory completeness,
     * applying advance deductions, and locking underlying attendance rows.
     */
    public function approveAndLockPeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            $lockedPeriod = PayrollPeriod::withoutGlobalScopes()
                ->where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPeriod->isLocked()) {
                return $lockedPeriod->fresh(['entries.worker', 'site']);
            }

            if ($lockedPeriod->status !== 'calculated') {
                throw ValidationException::withMessages([
                    'status' => 'Payroll period must be calculated before it can be approved and locked.',
                ]);
            }

            if ($lockedPeriod->calculated_by !== null && (int) $lockedPeriod->calculated_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'maker_checker' => 'Maker-checker separation of duties: The user who prepared or last modified this payroll period cannot approve and lock it.',
                ]);
            }

            if (Schema::hasColumn('payroll_entries', 'statutory_config_state')) {
                $unconfiguredEntries = PayrollEntry::withoutGlobalScopes()
                    ->with('worker')
                    ->where('payroll_period_id', $lockedPeriod->id)
                    ->where(function ($q) {
                        $q->whereNull('statutory_config_state')
                            ->orWhere('statutory_config_state', 'unconfigured');
                    })
                    ->get();

                if ($unconfiguredEntries->isNotEmpty()) {
                    $workerLabels = $unconfiguredEntries
                        ->map(function (PayrollEntry $entry) {
                            $worker = $entry->worker;

                            return $worker ? "{$worker->full_name} ({$worker->code})" : "Entry #{$entry->id}";
                        })
                        ->unique()
                        ->values()
                        ->implode(', ');

                    throw ValidationException::withMessages([
                        'statutory' => "Cannot approve and lock payroll while statutory PF/ESIC/PT configuration is incomplete ('unconfigured') for: {$workerLabels}. Configure each worker as Applicable or Not Applicable and recalculate first.",
                    ]);
                }
            }

            $entries = PayrollEntry::withoutGlobalScopes()
                ->where('payroll_period_id', $lockedPeriod->id)
                ->get();

            $endDate = Carbon::parse($lockedPeriod->end_date)->toDateString();

            foreach ($entries as $entry) {
                $toDeduct = (float) $entry->advance_deduction;
                if ($toDeduct <= 0) {
                    continue;
                }

                $advances = SalaryAdvance::withoutGlobalScopes()
                    ->where('company_id', $lockedPeriod->company_id)
                    ->where('worker_id', $entry->worker_id)
                    ->where('status', 'approved')
                    ->whereDate('advance_date', '<=', $endDate)
                    ->orderBy('advance_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($advances as $advance) {
                    if ($toDeduct <= 0) {
                        break;
                    }
                    $remaining = $advance->remainingBalance();
                    if ($remaining <= 0) {
                        continue;
                    }
                    $applied = min($remaining, $toDeduct);
                    $newDeducted = round((float) $advance->deducted_amount + $applied, 2);
                    $advance->update([
                        'deducted_amount' => $newDeducted,
                        'status' => $newDeducted >= (float) $advance->amount ? 'deducted' : 'approved',
                        'payroll_period_id' => $lockedPeriod->id,
                    ]);
                    $toDeduct = round($toDeduct - $applied, 2);
                }
            }

            // Lock attendance records in this period to 'payroll_locked'
            $attQuery = DailyAttendance::withoutGlobalScopes()
                ->where('company_id', $lockedPeriod->company_id)
                ->whereBetween('attendance_date', [
                    Carbon::parse($lockedPeriod->start_date)->toDateString(),
                    Carbon::parse($lockedPeriod->end_date)->toDateString(),
                ]);

            if ($lockedPeriod->site_id) {
                $attQuery->where('site_id', $lockedPeriod->site_id);
            }

            $attQuery->update([
                'lock_stage' => 'payroll_locked',
                'approval_status' => 'locked',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $lockedPeriod->update([
                'status' => 'locked',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            AuditLog::record(
                'payroll.period.locked',
                $lockedPeriod,
                ['status' => 'calculated'],
                ['status' => 'locked', 'approved_by' => $actor->id]
            );

            return $lockedPeriod->fresh(['entries.worker', 'site']);
        });
    }
}
