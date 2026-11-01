<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderRevision extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'work_order_id',
        'revision_number',
        'reason',
        'previous_base_value',
        'new_base_value',
        'items_snapshot',
        'proposed_items',
        'status',
        'changed_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'previous_base_value' => 'decimal:2',
            'new_base_value' => 'decimal:2',
            'items_snapshot' => 'array',
            'proposed_items' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

