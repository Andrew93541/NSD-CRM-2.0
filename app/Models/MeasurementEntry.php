<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'work_order_id',
        'work_order_item_id',
        'site_id',
        'ra_bill_id',
        'measured_on',
        'location_chainage',
        'nos',
        'length',
        'breadth',
        'depth_height',
        'direct_quantity',
        'computed_quantity',
        'hold_quantity',
        'photo_path',
        'remarks',
        'status',
        'recorded_by',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'nos' => 'decimal:3',
            'length' => 'decimal:3',
            'breadth' => 'decimal:3',
            'depth_height' => 'decimal:3',
            'direct_quantity' => 'decimal:3',
            'computed_quantity' => 'decimal:3',
            'hold_quantity' => 'decimal:3',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry) {
            $nos = (float) ($entry->nos ?? 1.0);
            if ($entry->direct_quantity !== null && (float) $entry->direct_quantity > 0 && $entry->length === null && $entry->breadth === null && $entry->depth_height === null) {
                $entry->computed_quantity = round($nos * (float) $entry->direct_quantity, 3);
            } elseif ($entry->length !== null || $entry->breadth !== null || $entry->depth_height !== null) {
                $l = $entry->length !== null ? (float) $entry->length : 1.0;
                $b = $entry->breadth !== null ? (float) $entry->breadth : 1.0;
                $d = $entry->depth_height !== null ? (float) $entry->depth_height : 1.0;
                $entry->computed_quantity = round($nos * $l * $b * $d, 3);
            } elseif ($entry->direct_quantity !== null) {
                $entry->computed_quantity = round($nos * (float) $entry->direct_quantity, 3);
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(WorkOrderItem::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function raBill(): BelongsTo
    {
        return $this->belongsTo(RaBill::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

