<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'code',
        'unit_of_measure',
        'minimum_stock_level',
        'standard_unit_cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock_level' => 'decimal:2',
            'standard_unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
