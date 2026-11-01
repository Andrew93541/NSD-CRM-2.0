<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaBill extends Model
{
    use HasFactory, BelongsToCompany;

    public const APPROVED_STATUSES = ['approved', 'invoiced', 'paid'];

    protected $fillable = [
        'company_id',
        'work_order_id',
        'site_id',
        'client_id',
        'ra_number',
        'ra_label',
        'invoice_number',
        'invoice_date',
        'period_from',
        'period_to',
        'work_order_revision',
        'previous_bill_amount',
        'current_bill_amount',
        'cumulative_bill_amount',
        'excess_unbilled_amount',
        'cgst_rate',
        'sgst_rate',
        'cgst_amount',
        'sgst_amount',
        'gross_amount',
        'retention_percent',
        'retention_deduction',
        'advance_recovery',
        'other_deductions',
        'rounding_mode',
        'net_payable',
        'status',
        'created_by',
        'site_manager_id',
        'site_manager_approved_at',
        'site_manager_notes',
        'senior_manager_id',
        'senior_manager_approved_at',
        'senior_manager_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'ra_number' => 'integer',
            'invoice_date' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'work_order_revision' => 'integer',
            'previous_bill_amount' => 'decimal:2',
            'current_bill_amount' => 'decimal:2',
            'cumulative_bill_amount' => 'decimal:2',
            'excess_unbilled_amount' => 'decimal:2',
            'cgst_rate' => 'decimal:2',
            'sgst_rate' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'retention_deduction' => 'decimal:2',
            'advance_recovery' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'site_manager_approved_at' => 'datetime',
            'senior_manager_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public static function formatOrdinalLabel(int $number): string
    {
        $mod100 = $number % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            $suffix = 'TH';
        } else {
            $suffix = match ($number % 10) {
                1 => 'ST',
                2 => 'ND',
                3 => 'RD',
                default => 'TH',
            };
        }

        return "{$number}{$suffix} RA BILL";
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::APPROVED_STATUSES, true);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RaBillItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MeasurementEntry::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(BillExport::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function siteManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'site_manager_id');
    }

    public function seniorManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'senior_manager_id');
    }
}

