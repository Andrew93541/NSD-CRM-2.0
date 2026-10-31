<?php

namespace App\Services\Workforce;

use App\Models\AttendanceFlag;
use App\Models\AttendanceLock;
use App\Models\AttendancePunch;
use App\Models\DailyAttendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AttendanceService
{
    /**
     * Records muster roll entries for a given site and date.
     * Prevents default time fabrication: empty clock inputs remain null.
     * Rejects direct edits if the site-date is locked (site_locked or payroll_locked).
     */
    public function recordMusterRoll(int $siteId, string $date, array $workerEntries, int $recordedByUserId): array
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Tenant context not established.');
        }

        if ($this->isSiteDateLocked($siteId, $date)) {
            throw new DomainException('Attendance for this site and date is locked and cannot be modified directly.');
        }

        $site = Site::findOrFail($siteId);
        $savedRecords = [];

        DB::transaction(function () use ($companyId, $site, $date, $workerEntries, $recordedByUserId, &$savedRecords) {
            foreach ($workerEntries as $workerId => $periods) {
                $periodList = isset($periods[0]) ? $periods : [$periods];

                foreach ($periodList as $idx => $entry) {
                    $status = $entry['status'] ?? 'present';
                    $clockIn = !empty($entry['clock_in']) ? Carbon::parse($entry['clock_in'])->format('H:i:s') : null;
                    $clockOut = !empty($entry['clock_out']) ? Carbon::parse($entry['clock_out'])->format('H:i:s') : null;

                    if ($status === 'absent') {
                        $clockIn = null;
                        $clockOut = null;
                    }

                    $query = DailyAttendance::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('site_id', $site->id)
                        ->where('worker_id', $workerId)
                        ->whereDate('attendance_date', $date);

                    if (!empty($entry['id'])) {
                        $attendance = $query->where('id', $entry['id'])->first();
                    } else {
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
                    if (Schema::hasColumn('daily_attendances', 'lock_stage')) {
                        $attendance->lock_stage = $attendance->lock_stage ?: 'submitted';
                    }
                    $attendance->save();

                    $savedRecords[] = $attendance;
                }
            }
        });

        return $savedRecords;
    }

    /**
     * Append-only punch recording using strictly server-generated timestamps.
     * Calculates geofence distance, updates DailyAttendance, and raises anomaly flags.
     */
    public function recordPunch(Worker $worker, Site $site, array $payload, ?int $markedByUserId = null): AttendancePunch
    {
        $companyId = $worker->company_id ?: TenantContext::getCompanyId();
        $serverNow = Carbon::now();
        $dateString = $serverNow->format('Y-m-d');

        if ($this->isSiteDateLocked($site->id, $dateString)) {
            throw new DomainException('Attendance for this site and date is locked.');
        }

        // Determine punch type ('in' or 'out')
        $requestedType = $payload['punch_type'] ?? 'toggle';
        $openAttendance = DailyAttendance::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('site_id', $site->id)
            ->where('worker_id', $worker->id)
            ->whereDate('attendance_date', $dateString)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->latest('id')
            ->first();

        if ($requestedType === 'toggle') {
            $punchType = $openAttendance ? 'out' : 'in';
        } else {
            $punchType = in_array($requestedType, ['in', 'out'], true) ? $requestedType : 'in';
        }

        // Calculate geofence distance if coordinates exist on both punch and site
        $lat = isset($payload['latitude']) && $payload['latitude'] !== '' ? (float) $payload['latitude'] : null;
        $lng = isset($payload['longitude']) && $payload['longitude'] !== '' ? (float) $payload['longitude'] : null;
        $distanceM = null;
        $withinGeofence = null;

        if ($lat !== null && $lng !== null && $site->latitude !== null && $site->longitude !== null) {
            $distanceM = round($this->calculateDistanceMeters(
                $lat,
                $lng,
                (float) $site->latitude,
                (float) $site->longitude
            ), 2);
            $radius = (int) ($site->geofence_radius_m ?: 200);
            $withinGeofence = $distanceM <= $radius;
        }

        return DB::transaction(function () use (
            $companyId,
            $worker,
            $site,
            $serverNow,
            $dateString,
            $punchType,
            $openAttendance,
            $lat,
            $lng,
            $distanceM,
            $withinGeofence,
            $payload,
            $markedByUserId
        ) {
            if ($punchType === 'out' && $openAttendance) {
                $attendance = $openAttendance;
                $attendance->clock_out = $serverNow->format('H:i:s');
                $attendance->save();

                // Update status to half_day if worked_hours < half_day threshold (default 4h) and > 0
                if ((float) $attendance->worked_hours > 0 && (float) $attendance->worked_hours < 4.0) {
                    $attendance->status = 'half_day';
                    $attendance->save();
                }
            } else {
                $attendance = DailyAttendance::create([
                    'company_id' => $companyId,
                    'site_id' => $site->id,
                    'project_id' => $site->project_id,
                    'worker_id' => $worker->id,
                    'attendance_date' => $dateString,
                    'clock_in' => $serverNow->format('H:i:s'),
                    'clock_out' => null,
                    'status' => 'present',
                    'approval_status' => 'pending_approval',
                    'lock_stage' => 'open',
                    'submitted_by' => $markedByUserId,
                    'submitted_at' => $serverNow,
                ]);
            }

            $punch = AttendancePunch::create([
                'company_id' => $companyId,
                'site_id' => $site->id,
                'project_id' => $site->project_id,
                'worker_id' => $worker->id,
                'daily_attendance_id' => $attendance->id,
                'punch_type' => $punchType,
                'punched_at' => $serverNow,
                'source' => $payload['source'] ?? 'self',
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_m' => isset($payload['accuracy_m']) ? (float) $payload['accuracy_m'] : null,
                'distance_m' => $distanceM,
                'within_geofence' => $withinGeofence,
                'device_id' => $payload['device_id'] ?? null,
                'ip_address' => $payload['ip_address'] ?? null,
                'marked_by' => $markedByUserId,
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->evaluatePunchAnomalies($punch, $worker, $site, $attendance, $dateString);

            return $punch;
        });
    }

    /**
     * Evaluates geofence, shared device, and impossible travel anomalies for a punch.
     */
    protected function evaluatePunchAnomalies(
        AttendancePunch $punch,
        Worker $worker,
        Site $site,
        DailyAttendance $attendance,
        string $dateString
    ): void {
        if (!Schema::hasTable('attendance_flags')) {
            return;
        }

        // 1. Outside Geofence Flag
        if ($punch->within_geofence === false) {
            $radius = (int) ($site->geofence_radius_m ?: 200);
            AttendanceFlag::create([
                'company_id' => $punch->company_id,
                'site_id' => $site->id,
                'worker_id' => $worker->id,
                'daily_attendance_id' => $attendance->id,
                'attendance_punch_id' => $punch->id,
                'attendance_date' => $dateString,
                'flag_type' => 'outside_geofence',
                'severity' => 'warning',
                'description' => sprintf(
                    'Punch recorded %.1f m away from %s center (allowed radius: %d m).',
                    (float) $punch->distance_m,
                    $site->code,
                    $radius
                ),
                'status' => 'open',
            ]);
        }

        // 2. Shared Device Flag (same device_id used by a different worker on self-punches today)
        if (!empty($punch->device_id) && $punch->source === 'self') {
            $otherWorkersOnDevice = AttendancePunch::withoutGlobalScopes()
                ->where('company_id', $punch->company_id)
                ->where('device_id', $punch->device_id)
                ->where('source', 'self')
                ->where('worker_id', '!=', $worker->id)
                ->whereDate('punched_at', $dateString)
                ->count();

            if ($otherWorkersOnDevice > 0) {
                AttendanceFlag::create([
                    'company_id' => $punch->company_id,
                    'site_id' => $site->id,
                    'worker_id' => $worker->id,
                    'daily_attendance_id' => $attendance->id,
                    'attendance_punch_id' => $punch->id,
                    'attendance_date' => $dateString,
                    'flag_type' => 'shared_device',
                    'severity' => 'critical',
                    'description' => sprintf(
                        'Device ID [%s] was used by multiple workers for self-punch on %s.',
                        $punch->device_id,
                        $dateString
                    ),
                    'status' => 'open',
                ]);
            }
        }

        // 3. Impossible Travel Flag (punches at two different sites on the same day > 80 km/h)
        $priorOtherSitePunch = AttendancePunch::withoutGlobalScopes()
            ->where('company_id', $punch->company_id)
            ->where('worker_id', $worker->id)
            ->where('site_id', '!=', $site->id)
            ->where('id', '!=', $punch->id)
            ->whereDate('punched_at', $dateString)
            ->with('site')
            ->latest('punched_at')
            ->first();

        if ($priorOtherSitePunch && $priorOtherSitePunch->site && $site->latitude !== null && $site->longitude !== null && $priorOtherSitePunch->site->latitude !== null && $priorOtherSitePunch->site->longitude !== null) {
            $distMeters = $this->calculateDistanceMeters(
                (float) $priorOtherSitePunch->site->latitude,
                (float) $priorOtherSitePunch->site->longitude,
                (float) $site->latitude,
                (float) $site->longitude
            );
            $distKm = $distMeters / 1000.0;
            $minutesDiff = max(1.0, abs($punch->punched_at->diffInSeconds($priorOtherSitePunch->punched_at)) / 60.0);
            $hoursDiff = $minutesDiff / 60.0;
            $speedKmh = $distKm / $hoursDiff;

            if ($distKm >= 2.0 && $speedKmh > 80.0) {
                AttendanceFlag::create([
                    'company_id' => $punch->company_id,
                    'site_id' => $site->id,
                    'worker_id' => $worker->id,
                    'daily_attendance_id' => $attendance->id,
                    'attendance_punch_id' => $punch->id,
                    'attendance_date' => $dateString,
                    'flag_type' => 'impossible_travel',
                    'severity' => 'critical',
                    'description' => sprintf(
                        'Impossible travel between %s and %s (%.1f km in %.0f mins, implied %.0f km/h).',
                        $priorOtherSitePunch->site->code,
                        $site->code,
                        $distKm,
                        $minutesDiff,
                        $speedKmh
                    ),
                    'status' => 'open',
                ]);
            }
        }
    }

    /**
     * Haversine distance in meters between two GPS coordinates.
     */
    public function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Checks whether a site + date is locked (site_locked or payroll_locked).
     */
    public function isSiteDateLocked(int $siteId, string $date): bool
    {
        if (!Schema::hasTable('attendance_locks')) {
            return false;
        }

        $lock = AttendanceLock::withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->whereDate('attendance_date', $date)
            ->first();

        return $lock ? $lock->isLocked() : false;
    }

    /**
     * Transitions the three-stage lock for a site and date.
     */
    public function transitionLockStage(
        Site $site,
        string $date,
        string $targetStage,
        int $userId,
        ?string $reason = null
    ): AttendanceLock {
        $companyId = $site->company_id ?: TenantContext::getCompanyId();
        $now = Carbon::now();

        return DB::transaction(function () use ($companyId, $site, $date, $targetStage, $userId, $reason, $now) {
            $lock = AttendanceLock::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('site_id', $site->id)
                ->whereDate('attendance_date', $date)
                ->first();

            if (!$lock) {
                $lock = new AttendanceLock([
                    'company_id' => $companyId,
                    'site_id' => $site->id,
                    'attendance_date' => $date,
                ]);
            }

            $lock->stage = $targetStage;

            if ($targetStage === 'submitted') {
                $lock->submitted_by = $userId;
                $lock->submitted_at = $now;
            } elseif ($targetStage === 'site_locked') {
                $lock->site_locked_by = $userId;
                $lock->site_locked_at = $now;
            } elseif ($targetStage === 'payroll_locked') {
                if (!$lock->site_locked_by) {
                    $lock->site_locked_by = $userId;
                    $lock->site_locked_at = $now;
                }
                $lock->payroll_locked_by = $userId;
                $lock->payroll_locked_at = $now;
            } elseif ($targetStage === 'open') {
                $lock->unlocked_by = $userId;
                $lock->unlocked_at = $now;
                $lock->unlock_reason = $reason;
            }

            $lock->save();

            // Sync DailyAttendance records for this site and date
            $updateAttrs = ['lock_stage' => $targetStage];
            if (in_array($targetStage, ['site_locked', 'payroll_locked'], true)) {
                $updateAttrs['approval_status'] = 'approved';
                $updateAttrs['approved_by'] = $userId;
                $updateAttrs['approved_at'] = $now;
            }

            DailyAttendance::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('site_id', $site->id)
                ->whereDate('attendance_date', $date)
                ->update($updateAttrs);

            return $lock;
        });
    }

    /**
     * Populates DailyAttendance records automatically when a LeaveRequest is approved.
     */
    public function applyApprovedLeave(LeaveRequest $leaveRequest, int $approvedByUserId): void
    {
        $worker = $leaveRequest->worker;
        $siteId = $leaveRequest->site_id ?: $worker?->assigned_site_id;
        if (!$worker || !$siteId) {
            return;
        }

        $site = Site::withoutGlobalScopes()->find($siteId);
        if (!$site) {
            return;
        }

        $period = CarbonPeriod::create(
            $leaveRequest->start_date->format('Y-m-d'),
            $leaveRequest->end_date->format('Y-m-d')
        );

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            if ($this->isSiteDateLocked($site->id, $dateStr)) {
                continue;
            }

            $existing = DailyAttendance::withoutGlobalScopes()
                ->where('company_id', $leaveRequest->company_id)
                ->where('site_id', $site->id)
                ->where('worker_id', $worker->id)
                ->whereDate('attendance_date', $dateStr)
                ->first();

            if (!$existing) {
                $existing = new DailyAttendance([
                    'company_id' => $leaveRequest->company_id,
                    'site_id' => $site->id,
                    'project_id' => $site->project_id,
                    'worker_id' => $worker->id,
                    'attendance_date' => $dateStr,
                ]);
            }

            $existing->status = $leaveRequest->is_paid ? 'paid_leave' : 'absent';
            $existing->clock_in = null;
            $existing->clock_out = null;
            $existing->worked_hours = 0.00;
            $existing->overtime_hours = 0.00;
            $existing->approval_status = 'approved';
            $existing->approved_by = $approvedByUserId;
            $existing->approved_at = Carbon::now();
            $existing->save();
        }
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
            'total_headcount' => $uniqueWorkerIds->count(),
            'total_hours' => round($totalHours, 2),
            'conflict_count' => $conflictCount,
            'by_worker_type' => $byType,
            'records' => $records,
        ];
    }

    /**
     * Date-Wise Attendance Register for Admin & Managers:
     * Lists all active workers (across all accessible sites or a selected site)
     * with their exact status (present, half_day, paid_leave, absent, holiday, unmarked),
     * clock_in/clock_out, worked_hours, overtime_hours, late_minutes, and summary KPIs.
     */
    public function getDateWiseAttendanceRegister(
        string $date,
        ?int $siteId = null,
        ?string $workerType = null,
        ?string $presenceFilter = null,
        ?string $search = null,
        ?array $accessibleSiteIds = null
    ): array {
        $companyId = TenantContext::getCompanyId();

        $workersQuery = Worker::where('status', 'active')
            ->with(['assignedSite', 'subcontractor', 'shift']);

        if ($companyId) {
            $workersQuery->where('company_id', $companyId);
        }

        if ($siteId) {
            $workersQuery->where(function ($q) use ($siteId, $date) {
                $q->where('assigned_site_id', $siteId)
                  ->orWhereHas('attendances', function ($aq) use ($siteId, $date) {
                      $aq->where('site_id', $siteId)->whereDate('attendance_date', $date);
                  });
            });
        } elseif ($accessibleSiteIds !== null) {
            $workersQuery->where(function ($q) use ($accessibleSiteIds, $date) {
                $q->whereIn('assigned_site_id', $accessibleSiteIds)
                  ->orWhereHas('attendances', function ($aq) use ($accessibleSiteIds, $date) {
                      $aq->whereIn('site_id', $accessibleSiteIds)->whereDate('attendance_date', $date);
                  });
            });
        }

        if ($workerType && in_array($workerType, ['employee', 'direct_labour', 'subcontractor_worker'], true)) {
            $workersQuery->where('worker_type', $workerType);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $workersQuery->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('trade_designation', 'like', $term);
            });
        }

        $workers = $workersQuery->orderBy('first_name')->orderBy('last_name')->get();
        $workerIds = $workers->pluck('id')->all();

        $attQuery = DailyAttendance::with(['site'])
            ->whereIn('worker_id', $workerIds)
            ->whereDate('attendance_date', $date);

        if ($siteId) {
            $attQuery->where('site_id', $siteId);
        } elseif ($accessibleSiteIds !== null) {
            $attQuery->whereIn('site_id', $accessibleSiteIds);
        }

        $attendancesByWorker = $attQuery->get()->groupBy('worker_id');

        $holidays = collect();
        if (Schema::hasTable('holidays')) {
            $holidays = Holiday::whereDate('holiday_date', $date)->get();
        }

        $rows = [];
        $summary = [
            'total_workers' => $workers->count(),
            'present_count' => 0,
            'half_day_count' => 0,
            'paid_leave_count' => 0,
            'absent_count' => 0,
            'unmarked_count' => 0,
            'holiday_count' => 0,
            'total_worked_hours' => 0.0,
            'total_overtime_hours' => 0.0,
            'by_worker_type_present' => [
                'employee' => 0,
                'direct_labour' => 0,
                'subcontractor_worker' => 0,
            ],
        ];

        foreach ($workers as $worker) {
            $workerAtts = $attendancesByWorker->get($worker->id, collect());
            $effectiveStatus = 'unmarked';
            $badgeCode = '—';

            if ($workerAtts->isNotEmpty()) {
                $statuses = $workerAtts->pluck('status')->all();
                if (in_array('present', $statuses, true)) {
                    $effectiveStatus = 'present';
                    $badgeCode = 'P';
                } elseif (in_array('half_day', $statuses, true)) {
                    $effectiveStatus = 'half_day';
                    $badgeCode = 'HD';
                } elseif (in_array('paid_leave', $statuses, true)) {
                    $effectiveStatus = 'paid_leave';
                    $badgeCode = 'PL';
                } elseif (in_array('absent', $statuses, true)) {
                    $effectiveStatus = 'absent';
                    $badgeCode = 'A';
                }
            } else {
                $workerSiteId = $siteId ?: $worker->assigned_site_id;
                $isHoliday = $holidays->contains(function ($h) use ($workerSiteId) {
                    return $h->site_id === null || (int) $h->site_id === (int) $workerSiteId;
                });
                if ($isHoliday) {
                    $effectiveStatus = 'holiday';
                    $badgeCode = 'H';
                }
            }

            $workedHours = round((float) $workerAtts->sum('worked_hours'), 2);
            $overtimeHours = round((float) $workerAtts->sum('overtime_hours'), 2);
            $lateMinutes = (int) $workerAtts->max('late_minutes');

            // Update overall summary before filtering rows by presenceFilter
            if ($effectiveStatus === 'present') {
                $summary['present_count']++;
                if (isset($summary['by_worker_type_present'][$worker->worker_type])) {
                    $summary['by_worker_type_present'][$worker->worker_type]++;
                }
            } elseif ($effectiveStatus === 'half_day') {
                $summary['half_day_count']++;
                if (isset($summary['by_worker_type_present'][$worker->worker_type])) {
                    $summary['by_worker_type_present'][$worker->worker_type]++;
                }
            } elseif ($effectiveStatus === 'paid_leave') {
                $summary['paid_leave_count']++;
            } elseif ($effectiveStatus === 'absent') {
                $summary['absent_count']++;
            } elseif ($effectiveStatus === 'holiday') {
                $summary['holiday_count']++;
            } else {
                $summary['unmarked_count']++;
            }

            $summary['total_worked_hours'] = round($summary['total_worked_hours'] + $workedHours, 2);
            $summary['total_overtime_hours'] = round($summary['total_overtime_hours'] + $overtimeHours, 2);

            if ($presenceFilter && $presenceFilter !== 'all' && $effectiveStatus !== $presenceFilter) {
                continue;
            }

            $clockIns = $workerAtts->pluck('clock_in')->filter()->map(fn ($t) => Carbon::parse($t)->format('H:i'))->values()->all();
            $clockOuts = $workerAtts->pluck('clock_out')->filter()->map(fn ($t) => Carbon::parse($t)->format('H:i'))->values()->all();
            $attendedSites = $workerAtts->map(fn ($a) => $a->site?->code ?: $a->site?->name)->filter()->unique()->values()->all();
            $lockStage = $workerAtts->first()?->lock_stage ?? 'open';
            $hasConflict = $workerAtts->contains(fn ($a) => (bool) $a->has_conflict);

            $rows[] = [
                'worker' => $worker,
                'status' => $effectiveStatus,
                'badge_code' => $badgeCode,
                'attended_sites' => $attendedSites,
                'assigned_site' => $worker->assignedSite,
                'clock_in' => !empty($clockIns) ? implode(', ', $clockIns) : null,
                'clock_out' => !empty($clockOuts) ? implode(', ', $clockOuts) : null,
                'worked_hours' => $workedHours,
                'overtime_hours' => $overtimeHours,
                'late_minutes' => $lateMinutes,
                'lock_stage' => $lockStage,
                'has_conflict' => $hasConflict,
                'records' => $workerAtts,
            ];
        }

        return [
            'date' => $date,
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * Month-Wise Attendance Matrix / Muster Grid for Admin & Managers:
     * Builds a 1..N day calendar matrix for the given YYYY-MM month with per-worker
     * Present (P), Half-Day (HD), Paid Leave (PL), Absent (A), Holiday (H), Worked Hours,
     * Overtime Hours, and per-day Present totals.
     */
    public function getMonthWiseAttendanceMatrix(
        string $month,
        ?int $siteId = null,
        ?string $workerType = null,
        ?string $search = null,
        ?array $accessibleSiteIds = null
    ): array {
        $companyId = TenantContext::getCompanyId();

        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $monthStart = Carbon::now()->startOfMonth();
            $month = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->copy()->endOfMonth();
        $startStr = $monthStart->format('Y-m-d');
        $endStr = $monthEnd->format('Y-m-d');

        $days = [];
        $period = CarbonPeriod::create($startStr, $endStr);
        foreach ($period as $d) {
            $days[] = [
                'date' => $d->format('Y-m-d'),
                'day_num' => $d->format('d'),
                'day_short' => substr($d->format('D'), 0, 2),
                'is_sunday' => $d->isSunday(),
                'is_today' => $d->isToday(),
            ];
        }

        $workersQuery = Worker::where('status', 'active')
            ->with(['assignedSite', 'subcontractor']);

        if ($companyId) {
            $workersQuery->where('company_id', $companyId);
        }

        if ($siteId) {
            $workersQuery->where(function ($q) use ($siteId, $startStr, $endStr) {
                $q->where('assigned_site_id', $siteId)
                  ->orWhereHas('attendances', function ($aq) use ($siteId, $startStr, $endStr) {
                      $aq->where('site_id', $siteId)
                         ->whereDate('attendance_date', '>=', $startStr)
                         ->whereDate('attendance_date', '<=', $endStr);
                  });
            });
        } elseif ($accessibleSiteIds !== null) {
            $workersQuery->where(function ($q) use ($accessibleSiteIds, $startStr, $endStr) {
                $q->whereIn('assigned_site_id', $accessibleSiteIds)
                  ->orWhereHas('attendances', function ($aq) use ($accessibleSiteIds, $startStr, $endStr) {
                      $aq->whereIn('site_id', $accessibleSiteIds)
                         ->whereDate('attendance_date', '>=', $startStr)
                         ->whereDate('attendance_date', '<=', $endStr);
                  });
            });
        }

        if ($workerType && in_array($workerType, ['employee', 'direct_labour', 'subcontractor_worker'], true)) {
            $workersQuery->where('worker_type', $workerType);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $workersQuery->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('trade_designation', 'like', $term);
            });
        }

        $workers = $workersQuery->orderBy('first_name')->orderBy('last_name')->get();
        $workerIds = $workers->pluck('id')->all();

        $attQuery = DailyAttendance::with(['site'])
            ->whereIn('worker_id', $workerIds)
            ->whereDate('attendance_date', '>=', $startStr)
            ->whereDate('attendance_date', '<=', $endStr);

        if ($siteId) {
            $attQuery->where('site_id', $siteId);
        } elseif ($accessibleSiteIds !== null) {
            $attQuery->whereIn('site_id', $accessibleSiteIds);
        }

        $allAttendances = $attQuery->get();

        $holidaysByDate = collect();
        if (Schema::hasTable('holidays')) {
            $holidaysByDate = Holiday::whereDate('holiday_date', '>=', $startStr)
                ->whereDate('holiday_date', '<=', $endStr)
                ->get()
                ->groupBy(fn ($h) => $h->holiday_date->format('Y-m-d'));
        }

        // Index attendances by [worker_id][Y-m-d]
        $attMatrix = [];
        foreach ($allAttendances as $att) {
            $dKey = Carbon::parse($att->attendance_date)->format('Y-m-d');
            $attMatrix[$att->worker_id][$dKey][] = $att;
        }

        $dailyTotals = [];
        foreach ($days as $dayInfo) {
            $dailyTotals[$dayInfo['date']] = [
                'present' => 0,
                'half_day' => 0,
                'paid_leave' => 0,
                'absent' => 0,
                'hours' => 0.0,
            ];
        }

        $summary = [
            'total_workers' => $workers->count(),
            'total_present_days' => 0,
            'total_half_days' => 0,
            'total_paid_leaves' => 0,
            'total_absent_days' => 0,
            'total_worked_hours' => 0.0,
            'total_overtime_hours' => 0.0,
        ];

        $workerRows = [];
        foreach ($workers as $worker) {
            $cells = [];
            $pCount = 0;
            $hdCount = 0;
            $plCount = 0;
            $aCount = 0;
            $hCount = 0;
            $wHours = 0.0;
            $otHours = 0.0;

            foreach ($days as $dayInfo) {
                $dKey = $dayInfo['date'];
                $dayAtts = collect($attMatrix[$worker->id][$dKey] ?? []);

                $status = 'unmarked';
                $code = '—';
                $hours = 0.0;
                $ot = 0.0;
                $siteLabel = null;

                if ($dayAtts->isNotEmpty()) {
                    $statuses = $dayAtts->pluck('status')->all();
                    $hours = round((float) $dayAtts->sum('worked_hours'), 2);
                    $ot = round((float) $dayAtts->sum('overtime_hours'), 2);
                    $siteLabel = $dayAtts->map(fn ($a) => $a->site?->code)->filter()->unique()->implode(', ');

                    if (in_array('present', $statuses, true)) {
                        $status = 'present';
                        $code = 'P';
                        $pCount++;
                        $dailyTotals[$dKey]['present']++;
                    } elseif (in_array('half_day', $statuses, true)) {
                        $status = 'half_day';
                        $code = 'HD';
                        $hdCount++;
                        $dailyTotals[$dKey]['half_day']++;
                    } elseif (in_array('paid_leave', $statuses, true)) {
                        $status = 'paid_leave';
                        $code = 'PL';
                        $plCount++;
                        $dailyTotals[$dKey]['paid_leave']++;
                    } elseif (in_array('absent', $statuses, true)) {
                        $status = 'absent';
                        $code = 'A';
                        $aCount++;
                        $dailyTotals[$dKey]['absent']++;
                    }

                    $wHours = round($wHours + $hours, 2);
                    $otHours = round($otHours + $ot, 2);
                    $dailyTotals[$dKey]['hours'] = round($dailyTotals[$dKey]['hours'] + $hours, 2);
                } else {
                    $dayHolidays = $holidaysByDate->get($dKey, collect());
                    $workerSiteId = $siteId ?: $worker->assigned_site_id;
                    $isHoliday = $dayHolidays->contains(function ($h) use ($workerSiteId) {
                        return $h->site_id === null || (int) $h->site_id === (int) $workerSiteId;
                    });
                    if ($isHoliday) {
                        $status = 'holiday';
                        $code = 'H';
                        $hCount++;
                    }
                }

                $cells[$dKey] = [
                    'status' => $status,
                    'code' => $code,
                    'hours' => $hours,
                    'ot' => $ot,
                    'site_code' => $siteLabel,
                ];
            }

            $summary['total_present_days'] += $pCount;
            $summary['total_half_days'] += $hdCount;
            $summary['total_paid_leaves'] += $plCount;
            $summary['total_absent_days'] += $aCount;
            $summary['total_worked_hours'] = round($summary['total_worked_hours'] + $wHours, 2);
            $summary['total_overtime_hours'] = round($summary['total_overtime_hours'] + $otHours, 2);

            $effectiveDays = $pCount + ($hdCount * 0.5) + $plCount;

            $workerRows[] = [
                'worker' => $worker,
                'assigned_site' => $worker->assignedSite,
                'cells' => $cells,
                'present_days' => $pCount,
                'half_days' => $hdCount,
                'paid_leaves' => $plCount,
                'absent_days' => $aCount,
                'holiday_days' => $hCount,
                'effective_days' => $effectiveDays,
                'worked_hours' => $wHours,
                'overtime_hours' => $otHours,
            ];
        }

        return [
            'month' => $month,
            'month_label' => $monthStart->format('F Y'),
            'prev_month' => $monthStart->copy()->subMonth()->format('Y-m'),
            'next_month' => $monthStart->copy()->addMonth()->format('Y-m'),
            'start_date' => $startStr,
            'end_date' => $endStr,
            'days' => $days,
            'summary' => $summary,
            'daily_totals' => $dailyTotals,
            'rows' => $workerRows,
        ];
    }
}
