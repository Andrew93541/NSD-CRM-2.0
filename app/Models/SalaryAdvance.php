<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdvance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'worker_id',
        'site_id',
        'advance_date',
        'amount',
        'deducted_amount',
        'status',
        'reason',
        'approved_by',
        'payroll_period_id',
    ];

    protected function casts(): array
    {
        return [
            'advance_date' => 'date',
            'amount' => 'decimal:2',
            'deducted_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function remainingBalance(): float
    {
        return max(0.0, round((float) $this->amount - (float) $this->deducted_amount, 2));
    }
}

