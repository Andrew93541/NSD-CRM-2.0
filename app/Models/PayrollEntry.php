<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'worker_id',
        'site_id',
        'pay_type',
        'present_days',
        'half_days',
        'paid_leave_days',
        'holiday_days',
        'effective_days',
        'worked_hours',
        'overtime_hours',
        'base_rate',
        'overtime_rate',
        'base_pay',
        'overtime_pay',
        'bonus_amount',
        'gross_pay',
        'advance_deduction',
        'other_deduction',
        'deduction_notes',
        'net_pay',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'present_days' => 'decimal:2',
            'half_days' => 'decimal:2',
            'paid_leave_days' => 'decimal:2',
            'holiday_days' => 'decimal:2',
            'effective_days' => 'decimal:2',
            'worked_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'base_rate' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
            'base_pay' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

