<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'site_id',
        'period_code',
        'title',
        'start_date',
        'end_date',
        'status',
        'total_workers',
        'total_gross_amount',
        'total_deductions',
        'total_net_amount',
        'calculated_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_workers' => 'integer',
            'total_gross_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class)->orderBy('id');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'locked', 'paid'], true);
    }
}

