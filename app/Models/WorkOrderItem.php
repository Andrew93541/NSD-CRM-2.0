<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'work_order_id',
        'sr_no',
        'service_code',
        'sac_code',
        'description',
        'specification',
        'unit',
        'ordered_quantity',
        'rate',
        'line_amount',
        'completion_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sr_no' => 'string',
            'ordered_quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'line_amount' => 'decimal:2',
            'completion_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkOrderItem $item) {
            $item->sr_no = (string) $item->sr_no;
            $qty = round((float) $item->ordered_quantity, 3);
            $rate = round((float) $item->rate, 2);
            $item->ordered_quantity = $qty;
            $item->rate = $rate;
            $item->line_amount = round($qty * $rate, 2);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MeasurementEntry::class);
    }

    public function raBillItems(): HasMany
    {
        return $this->hasMany(RaBillItem::class);
    }

    public function approvedBilledQuantity(): float
    {
        return round((float) $this->raBillItems()
            ->whereHas('raBill', fn ($q) => $q->whereIn('status', RaBill::APPROVED_STATUSES))
            ->sum('current_billable_quantity'), 3);
    }

    public function latestUnbilledExcessQuantity(): float
    {
        $latestItem = $this->raBillItems()
            ->whereHas('raBill', fn ($q) => $q->whereIn('status', RaBill::APPROVED_STATUSES))
            ->orderByDesc('id')
            ->first();

        return $latestItem ? round((float) $latestItem->excess_quantity, 3) : 0.0;
    }

    public function balanceQuantity(): float
    {
        return max(0.0, round((float) $this->ordered_quantity - $this->approvedBilledQuantity(), 3));
    }

    public function consumptionPercent(): float
    {
        $ordered = (float) $this->ordered_quantity;
        if ($ordered <= 0) {
            return 0.0;
        }

        return min(100.0, round(($this->approvedBilledQuantity() / $ordered) * 100, 2));
    }

    public function warningLevel(): string
    {
        $pct = $this->consumptionPercent();
        if ($pct >= 100.0 || $this->latestUnbilledExcessQuantity() > 0) {
            return 'cap_100';
        }
        if ($pct >= 90.0) {
            return 'warning_90';
        }
        if ($pct >= 80.0) {
            return 'warning_80';
        }

        return 'normal';
    }
}

