<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaBillItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'ra_bill_id',
        'work_order_item_id',
        'sr_no',
        'service_code',
        'sac_code',
        'description',
        'specification',
        'unit',
        'ordered_quantity',
        'rate',
        'order_amount',
        'previous_quantity',
        'claimed_quantity',
        'hold_quantity',
        'effective_claimed_quantity',
        'remaining_available_quantity',
        'current_billable_quantity',
        'excess_quantity',
        'cumulative_quantity',
        'balance_quantity',
        'consumption_percent',
        'warning_level',
        'previous_amount',
        'current_amount',
        'cumulative_amount',
        'excess_amount',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sr_no' => 'string',
            'ordered_quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'order_amount' => 'decimal:2',
            'previous_quantity' => 'decimal:3',
            'claimed_quantity' => 'decimal:3',
            'hold_quantity' => 'decimal:3',
            'effective_claimed_quantity' => 'decimal:3',
            'remaining_available_quantity' => 'decimal:3',
            'current_billable_quantity' => 'decimal:3',
            'excess_quantity' => 'decimal:3',
            'cumulative_quantity' => 'decimal:3',
            'balance_quantity' => 'decimal:3',
            'consumption_percent' => 'decimal:2',
            'previous_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'cumulative_amount' => 'decimal:2',
            'excess_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function raBill(): BelongsTo
    {
        return $this->belongsTo(RaBill::class);
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(WorkOrderItem::class);
    }
}

