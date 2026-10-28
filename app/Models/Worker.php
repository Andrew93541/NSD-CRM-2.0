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

    protected $fillable = [
        'company_id',
        'worker_type',
        'subcontractor_id',
        'code',
        'first_name',
        'last_name',
        'phone',
        'aadhaar_last_four',
        'trade_designation',
        'assigned_site_id',
        'daily_rate',
        'status',
        'joining_date',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'joining_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
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
