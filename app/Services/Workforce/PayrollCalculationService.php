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

class PayrollCalculationService
{
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

        if ($advanceDeductionOverride !== null) {
            $advanceDeduction = round(max(0.0, $advanceDeductionOverride), 2);
        } else {
            $pendingAdvances = SalaryAdvance::withoutGlobalScopes()
                ->where('company_id', $worker->company_id)
                ->where('worker_id', $worker->id)
                ->where('status', 'approved')
                ->whereDate('advance_date', '<=', $endDate->toDateString())
                ->get();

            $outstandingAdvance = round((float) $pendingAdvances->sum(fn (SalaryAdvance $adv) => $adv->remainingBalance()), 2);
            $advanceDeduction = min($grossPay, $outstandingAdvance);
        }

        $otherDeduction = round(max(0.0, $existingOtherDeduction), 2);
        $netPay = max(0.0, round($grossPay - $advanceDeduction - $otherDeduction, 2));

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
            'net_pay' => $netPay,
        ];
    }

    /**
     * Calculate or recalculate all entries for a PayrollPeriod.
     */
    public function calculatePeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            $startDate = Carbon::parse($period->start_date)->startOfDay();
            $endDate = Carbon::parse($period->end_date)->endOfDay();

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

                PayrollEntry::withoutGlobalScopes()->updateOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'worker_id' => $worker->id,
                    ],
                    array_merge($metrics, [
                        'company_id' => $period->company_id,
                        'site_id' => $period->site_id ?: $worker->assigned_site_id,
                        'deduction_notes' => $existingEntry?->deduction_notes,
                        'payment_status' => $existingEntry?->payment_status ?? 'pending',
                    ])
                );

                $totalWorkers++;
                $totalGross += $metrics['gross_pay'];
                $totalDeductions += ($metrics['advance_deduction'] + $metrics['other_deduction']);
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
     * Approve & Lock a PayrollPeriod, applying advance deductions and locking underlying attendance rows.
     */
    public function approveAndLockPeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            if ($period->status === 'draft') {
                $period = $this->calculatePeriod($period, $actor);
            }

            $entries = PayrollEntry::withoutGlobalScopes()
                ->where('payroll_period_id', $period->id)
                ->get();

            $endDate = Carbon::parse($period->end_date)->toDateString();

            foreach ($entries as $entry) {
                $toDeduct = (float) $entry->advance_deduction;
                if ($toDeduct <= 0) {
                    continue;
                }

                $advances = SalaryAdvance::withoutGlobalScopes()
                    ->where('company_id', $period->company_id)
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
                        'payroll_period_id' => $period->id,
                    ]);
                    $toDeduct = round($toDeduct - $applied, 2);
                }
            }

            // Lock attendance records in this period to 'payroll_locked'
            $attQuery = DailyAttendance::withoutGlobalScopes()
                ->where('company_id', $period->company_id)
                ->whereBetween('attendance_date', [
                    Carbon::parse($period->start_date)->toDateString(),
                    Carbon::parse($period->end_date)->toDateString(),
                ]);

            if ($period->site_id) {
                $attQuery->where('site_id', $period->site_id);
            }

            $attQuery->update([
                'lock_stage' => 'payroll_locked',
                'approval_status' => 'locked',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $period->update([
                'status' => 'locked',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            AuditLog::record(
                'payroll.period.locked',
                $period,
                ['status' => 'calculated'],
                ['status' => 'locked', 'approved_by' => $actor->id]
            );

            return $period->fresh(['entries.worker', 'site']);
        });
    }
}

