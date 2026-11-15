<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use BelongsToCompany, HasFactory;

    public const VENDOR_TYPES = [
        'material_supplier' => 'Material Supplier',
        'equipment_rental' => 'Equipment & Machinery Rental',
        'service_vendor' => 'Service & Transport Vendor',
        'hardware_electrical' => 'Hardware, Plumbing & Electrical',
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'vendor_type',
        'contact_person',
        'phone',
        'email',
        'gstin',
        'pan',
        'address',
        'payment_terms_days',
        'bank_name',
        'bank_account_no',
        'bank_ifsc',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
