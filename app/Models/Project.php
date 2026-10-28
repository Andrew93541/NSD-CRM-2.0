<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'client_name',
        'manager_id',
        'description',
        'status',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    // Compatibility aliases for end_date / target_completion_date
    public function getTargetCompletionDateAttribute(): mixed
    {
        return $this->end_date;
    }

    public function setTargetCompletionDateAttribute(mixed $value): void
    {
        $this->attributes['end_date'] = $value;
    }
}
