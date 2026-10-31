<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'daily_attendance_id',
        'requested_by',
        'reviewed_by',
        'previous_status',
        'proposed_status',
        'previous_clock_in',
        'proposed_clock_in',
        'previous_clock_out',
        'proposed_clock_out',
        'previous_site_id',
        'proposed_site_id',
        'previous_worked_hours',
        'proposed_worked_hours',
        'previous_overtime_hours',
        'proposed_overtime_hours',
        'reason',
        'status',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_worked_hours' => 'decimal:2',
            'proposed_worked_hours' => 'decimal:2',
            'previous_overtime_hours' => 'decimal:2',
            'proposed_overtime_hours' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(DailyAttendance::class, 'daily_attendance_id');
    }

    public function dailyAttendance(): BelongsTo
    {
        return $this->belongsTo(DailyAttendance::class, 'daily_attendance_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function previousSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'previous_site_id');
    }

    public function proposedSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'proposed_site_id');
    }
}
