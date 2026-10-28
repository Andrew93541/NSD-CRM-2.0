<?php

namespace App\Services\Workforce;

use App\Models\DailyAttendance;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceService
{
    /**
     * Records muster roll entries for a given site and date.
     * Prevents default time fabrication: empty clock inputs remain null.
     */
    public function recordMusterRoll(int $siteId, string $date, array $workerEntries, int $recordedByUserId): array
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Tenant context not established.');
        }

        $site = Site::findOrFail($siteId);
        $savedRecords = [];

        DB::transaction(function () use ($companyId, $site, $date, $workerEntries, $recordedByUserId, &$savedRecords) {
            foreach ($workerEntries as $workerId => $periods) {
                // If periods is a flat array of fields or list of periods
                $periodList = isset($periods[0]) ? $periods : [$periods];

                foreach ($periodList as $idx => $entry) {
                    $status = $entry['status'] ?? 'present';
                    $clockIn = !empty($entry['clock_in']) ? Carbon::parse($entry['clock_in'])->format('H:i:s') : null;
                    $clockOut = !empty($entry['clock_out']) ? Carbon::parse($entry['clock_out'])->format('H:i:s') : null;

                    // If user was absent, skip empty clock times
                    if ($status === 'absent') {
                        $clockIn = null;
                        $clockOut = null;
                    }

                    // Existing record lookup or create
                    $query = DailyAttendance::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('site_id', $site->id)
                        ->where('worker_id', $workerId)
                        ->whereDate('attendance_date', $date);

                    if (!empty($entry['id'])) {
                        $attendance = $query->where('id', $entry['id'])->first();
                    } else {
                        // Match on clock_in if present to allow multiple distinct periods
                        $attendance = $query->where('clock_in', $clockIn)->first();
                    }

                    if (!$attendance) {
                        $attendance = new DailyAttendance([
                            'company_id' => $companyId,
                            'site_id' => $site->id,
                            'project_id' => $site->project_id,
                            'worker_id' => $workerId,
                            'attendance_date' => $date,
                        ]);
                    }

                    $attendance->clock_in = $clockIn;
                    $attendance->clock_out = $clockOut;
                    $attendance->status = $status;
                    $attendance->submitted_by = $recordedByUserId;
                    $attendance->submitted_at = Carbon::now();
                    $attendance->approval_status = $attendance->approval_status ?? 'pending_approval';
                    $attendance->save();

                    $savedRecords[] = $attendance;
                }
            }
        });

        return $savedRecords;
    }

    /**
     * Daily workforce summary without double-counting workers across multiple sites.
     */
    public function getDailyAttendanceAggregation(string $date, ?int $siteId = null): array
    {
        $companyId = TenantContext::getCompanyId();

        $query = DailyAttendance::where('company_id', $companyId)
            ->whereDate('attendance_date', $date);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $records = $query->with(['worker.subcontractor', 'site'])->get();

        $uniqueWorkerIds = $records->pluck('worker_id')->unique();
        $totalHours = $records->sum('worked_hours');
        $conflictCount = $records->where('has_conflict', true)->count();

        $byType = [
            'employee' => 0,
            'direct_labour' => 0,
            'subcontractor_worker' => 0,
        ];

        $workers = Worker::whereIn('id', $uniqueWorkerIds)->get()->keyBy('id');
        foreach ($uniqueWorkerIds as $wId) {
            $w = $workers->get($wId);
            if ($w && isset($byType[$w->worker_type])) {
                $byType[$w->worker_type]++;
            }
        }

        return [
            'date' => $date,
            'total_headcount' => $uniqueWorkerIds->count(), // Unique worker count prevents double counting
            'total_hours' => round($totalHours, 2),
            'conflict_count' => $conflictCount,
            'by_worker_type' => $byType,
            'records' => $records,
        ];
    }
}
