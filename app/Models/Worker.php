<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use HasFactory, BelongsToCompany;

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        'prefer_not_to_say' => 'Prefer Not to Say',
    ];

    public const DEPARTMENTS = [
        'Civil & Earthworks',
        'Structural & Masonry',
        'Plant & Heavy Machinery',
        'Electrical & MEP',
        'Fabrication & Welding',
        'Quality & Safety (HSE)',
        'Logistics & Stores',
        'Site Administration',
        'General Labour',
    ];

    public const SKILL_LEVELS = [
        'unskilled' => 'Unskilled',
        'semi_skilled' => 'Semi-Skilled',
        'skilled' => 'Skilled',
        'highly_skilled' => 'Highly Skilled',
        'supervisor' => 'Supervisor / Foreman',
    ];

    public const PAY_TYPES = [
        'daily' => 'Daily Wage',
        'monthly' => 'Monthly Salary',
        'hourly' => 'Hourly Rate',
        'contract' => 'Fixed / Contract',
    ];

    protected $fillable = [
        'company_id',
        'worker_type',
        'subcontractor_id',
        'code',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'phone',
        'alternate_phone',
        'email',
        'address',
        'aadhaar_last_four',
        'trade_designation',
        'department',
        'skill_level',
        'assigned_site_id',
        'pay_type',
        'daily_rate',
        'monthly_salary',
        'hourly_rate',
        'contract_rate',
        'overtime_rate',
        'status',
        'joining_date',
        'exit_date',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'upi_id',
        'pin',
        'must_change_pin',
        'qr_token',
    ];

    protected $hidden = [
        'pin',
    ];

    protected static function booted(): void
    {
        static::creating(function (Worker $worker) {
            if (empty($worker->qr_token)) {
                $worker->qr_token = \Illuminate\Support\Str::random(40);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'contract_rate' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'exit_date' => 'date',
            'must_change_pin' => 'boolean',
        ];
    }

    public function payTypeLabel(): string
    {
        $type = $this->pay_type ?: 'daily';

        return __(self::PAY_TYPES[$type] ?? ucfirst($type));
    }

    public function primaryCompensationRate(): float
    {
        return match ($this->pay_type ?: 'daily') {
            'monthly' => (float) ($this->monthly_salary ?: $this->daily_rate * 30),
            'hourly' => (float) ($this->hourly_rate ?: ($this->daily_rate / 8)),
            'contract' => (float) ($this->contract_rate ?: $this->daily_rate),
            default => (float) $this->daily_rate,
        };
    }

    public function primaryCompensationUnit(): string
    {
        return match ($this->pay_type ?: 'daily') {
            'monthly' => '/ month',
            'hourly' => '/ hr',
            'contract' => '/ contract',
            default => '/ day',
        };
    }

    public function ensureQrToken(): string
    {
        if (empty($this->qr_token)) {
            $this->qr_token = \Illuminate\Support\Str::random(40);
            $this->saveQuietly();
        }

        return $this->qr_token;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function genderLabel(): ?string
    {
        if (!$this->gender) {
            return null;
        }

        return __(self::GENDERS[$this->gender] ?? ucfirst(str_replace('_', ' ', $this->gender)));
    }

    public function skillLevelLabel(): ?string
    {
        if (!$this->skill_level) {
            return null;
        }

        return __(self::SKILL_LEVELS[$this->skill_level] ?? ucfirst(str_replace('_', ' ', $this->skill_level)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function assignedSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'assigned_site_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function siteAssignments(): HasMany
    {
        return $this->hasMany(SiteAssignment::class)->latest('start_date')->latest('id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class)->latest('punched_at');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(AttendanceFlag::class)->latest('attendance_date');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest('start_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class)->latest();
    }

    public function payrollEntries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class)->latest();
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class)->latest('advance_date');
    }

    // Compatibility aliases
    public function getWorkerCodeAttribute(): ?string
    {
        return $this->code;
    }

    public function setWorkerCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value;
    }

    public function getTradeSkillAttribute(): ?string
    {
        return $this->trade_designation;
    }

    public function setTradeSkillAttribute(?string $value): void
    {
        $this->attributes['trade_designation'] = $value;
    }

    public function getDefaultSiteIdAttribute(): ?int
    {
        return $this->assigned_site_id;
    }

    public function setDefaultSiteIdAttribute(?int $value): void
    {
        $this->attributes['assigned_site_id'] = $value;
    }
}
