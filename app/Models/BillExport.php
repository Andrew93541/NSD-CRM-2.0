<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillExport extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'work_order_id',
        'ra_bill_id',
        'export_type',
        'file_name',
        'file_path',
        'invoice_number',
        'exported_by',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function raBill(): BelongsTo
    {
        return $this->belongsTo(RaBill::class);
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}

