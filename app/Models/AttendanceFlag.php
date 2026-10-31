<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceFlag extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = [
        'outside_geofence' => 'Outside Site Geofence',
        'shared_device' => 'Shared Device / Proxy Punch',
        'identical_crew_times' => 'Identical Crew Timestamps',
        'impossible_travel' => 'Impossible Travel Between Sites',
        'overlapping_periods' => 'Overlapping Shift Periods',
    ];

    protected $fillable = [
        'company_id',
        'site_id',
        'worker_id',
        'daily_attendance_id',
        'attendance_punch_id',
        'attendance_date',
        'flag_type',
        'severity',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->flag_type] ?? ucfirst(str_replace('_', ' ', $this->flag_type)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(DailyAttendance::class, 'daily_attendance_id');
    }

    public function punch(): BelongsTo
    {
        return $this->belongsTo(AttendancePunch::class, 'attendance_punch_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

