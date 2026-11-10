<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLock extends Model
{
    use HasFactory, BelongsToCompany;

    public const STAGES = [
        'open' => 'Open',
        'submitted' => 'Supervisor Submitted',
        'site_locked' => 'Site Manager Locked',
        'payroll_locked' => 'Payroll Locked',
    ];

    protected $fillable = [
        'company_id',
        'site_id',
        'attendance_date',
        'stage',
        'submitted_by',
        'submitted_at',
        'site_locked_by',
        'site_locked_at',
        'payroll_locked_by',
        'payroll_locked_at',
        'unlocked_by',
        'unlocked_at',
        'unlock_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'submitted_at' => 'datetime',
            'site_locked_at' => 'datetime',
            'payroll_locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AttendanceLock $lock) {
            if ($lock->isDirty(['company_id', 'site_id', 'attendance_date'])) {
                throw new \LogicException('AttendanceLock identity columns (company_id, site_id, attendance_date) cannot be altered.');
            }

            if (!array_key_exists((string) $lock->stage, self::STAGES)) {
                throw new \LogicException("Invalid AttendanceLock stage [{$lock->stage}].");
            }

            $originalStage = (string) $lock->getOriginal('stage');
            $targetStage = (string) $lock->stage;

            if ($originalStage === 'payroll_locked' && $targetStage !== 'payroll_locked') {
                if ($targetStage !== 'open' || empty($lock->unlocked_by) || strlen(trim((string) $lock->unlock_reason)) < 5) {
                    throw new \LogicException('A payroll-locked attendance period cannot be modified or downgraded without an authorized administrator unlock and reason.');
                }
            }

            if ($originalStage === 'site_locked' && $targetStage === 'submitted') {
                throw new \LogicException('A site-locked attendance period cannot be downgraded to submitted without an authorized unlock.');
            }

            if ($originalStage === 'site_locked' && $targetStage === 'open') {
                if (empty($lock->unlocked_by) || strlen(trim((string) $lock->unlock_reason)) < 5) {
                    throw new \LogicException('Unlocking a site-locked attendance period requires an authorized user and unlock reason.');
                }
            }
        });

        static::deleting(function () {
            throw new \LogicException('AttendanceLock records are immutable audit controls and cannot be deleted.');
        });
    }

    public function isLocked(): bool
    {
        return in_array($this->stage, ['site_locked', 'payroll_locked'], true);
    }

    public function isPayrollLocked(): bool
    {
        return $this->stage === 'payroll_locked';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function siteLocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'site_locked_by');
    }

    public function payrollLocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payroll_locked_by');
    }

    public function unlocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}

