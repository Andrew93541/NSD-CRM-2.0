<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'site_id',
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'half_day_hours',
        'full_day_hours',
        'overtime_after_hours',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'half_day_hours' => 'decimal:2',
            'full_day_hours' => 'decimal:2',
            'overtime_after_hours' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

