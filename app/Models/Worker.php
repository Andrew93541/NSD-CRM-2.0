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
        'assigned_site_id',
        'daily_rate',
        'status',
        'joining_date',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
        ];
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

    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class)->latest();
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
