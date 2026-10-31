<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyAttendance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'site_id',
        'project_id',
        'worker_id',
        'shift_id',
        'attendance_date',
        'clock_in',
        'clock_out',
        'status',
        'worked_hours',
        'overtime_hours',
        'late_minutes',
        'has_conflict',
        'conflict_reason',
        'approval_status',
        'lock_stage',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'has_conflict' => 'boolean',
            'worked_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'late_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DailyAttendance $attendance) {
            $site = $attendance->site_id ? Site::withoutGlobalScopes()->find($attendance->site_id) : null;

            // Inherit project_id from site if empty
            if (empty($attendance->project_id) && $site) {
                $attendance->project_id = $site->project_id;
            }

            // Calculate worked_hours, overtime_hours, and late_minutes if clock_in and clock_out are present
            if ($attendance->clock_in && $attendance->clock_out) {
                $in = Carbon::parse($attendance->clock_in);
                $out = Carbon::parse($attendance->clock_out);
                if ($out->greaterThanOrEqualTo($in)) {
                    $attendance->worked_hours = round($in->diffInMinutes($out) / 60, 2);
                } else {
                    $attendance->worked_hours = 0.00;
                }

                // Resolve shift or site shift rules
                $shift = $attendance->shift_id ? Shift::withoutGlobalScopes()->find($attendance->shift_id) : null;
                $overtimeAfter = $shift ? (float) $shift->overtime_after_hours : (float) ($site?->shift_hours ?? 8.00);
                if ($overtimeAfter <= 0) {
                    $overtimeAfter = 8.00;
                }
                $attendance->overtime_hours = round(max(0.0, (float) $attendance->worked_hours - $overtimeAfter), 2);

                $shiftStartStr = $shift?->start_time ?? $site?->shift_start_time ?? '09:00:00';
                $graceMins = $shift ? (int) $shift->grace_minutes : 15;
                if ($shiftStartStr) {
                    $shiftStart = Carbon::parse($shiftStartStr);
                    $graceLimit = $shiftStart->copy()->addMinutes($graceMins);
                    $inTimeOnly = Carbon::parse($in->format('H:i:s'));
                    if ($inTimeOnly->greaterThan($graceLimit)) {
                        $attendance->late_minutes = (int) $shiftStart->diffInMinutes($inTimeOnly);
                    } else {
                        $attendance->late_minutes = 0;
                    }
                }
            } elseif ($attendance->status === 'absent') {
                $attendance->worked_hours = 0.00;
                $attendance->overtime_hours = 0.00;
                $attendance->late_minutes = 0;
            }
        });

        static::saved(function (DailyAttendance $attendance) {
            static::recalculateConflictsForWorkerDate(
                $attendance->company_id,
                $attendance->worker_id,
                $attendance->attendance_date->format('Y-m-d')
            );
        });

        static::deleted(function (DailyAttendance $attendance) {
            static::recalculateConflictsForWorkerDate(
                $attendance->company_id,
                $attendance->worker_id,
                $attendance->attendance_date->format('Y-m-d')
            );
        });
    }

    public static function recalculateConflictsForWorkerDate(int $companyId, int $workerId, string $dateString): void
    {
        $records = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('worker_id', $workerId)
            ->whereDate('attendance_date', $dateString)
            ->get();

        $conflictMap = [];

        foreach ($records as $recordA) {
            if (!$recordA->clock_in || !$recordA->clock_out) {
                continue;
            }

            $startA = Carbon::parse($recordA->clock_in);
            $endA = Carbon::parse($recordA->clock_out);

            foreach ($records as $recordB) {
                if ($recordA->id === $recordB->id) {
                    continue;
                }

                if (!$recordB->clock_in || !$recordB->clock_out) {
                    continue;
                }

                $startB = Carbon::parse($recordB->clock_in);
                $endB = Carbon::parse($recordB->clock_out);

                // Overlap condition: startA < endB and endA > startB
                if ($startA->lessThan($endB) && $endA->greaterThan($startB)) {
                    $overlapWithSite = $recordB->site_id === $recordA->site_id ? 'Same Site' : 'Site #' . $recordB->site_id;
                    $msg = 'Overlaps with record #' . $recordB->id . ' (' . $overlapWithSite . ': ' . $recordB->clock_in . ' - ' . $recordB->clock_out . ')';
                    $conflictMap[$recordA->id] = isset($conflictMap[$recordA->id]) 
                        ? $conflictMap[$recordA->id] . '; ' . $msg 
                        : $msg;
                }
            }
        }

        foreach ($records as $record) {
            $hasConflict = isset($conflictMap[$record->id]);
            $reason = $conflictMap[$record->id] ?? null;

            if ($record->has_conflict !== $hasConflict || $record->conflict_reason !== $reason) {
                static::withoutEvents(function () use ($record, $hasConflict, $reason) {
                    $record->update([
                        'has_conflict' => $hasConflict,
                        'conflict_reason' => $reason,
                    ]);
                });
            }

            if ($hasConflict && $reason && \Illuminate\Support\Facades\Schema::hasTable('attendance_flags')) {
                AttendanceFlag::withoutGlobalScopes()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'daily_attendance_id' => $record->id,
                        'flag_type' => 'overlapping_periods',
                    ],
                    [
                        'site_id' => $record->site_id,
                        'worker_id' => $workerId,
                        'attendance_date' => $dateString,
                        'severity' => 'critical',
                        'description' => $reason,
                        'status' => 'open',
                    ]
                );
            }
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(AttendanceFlag::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
