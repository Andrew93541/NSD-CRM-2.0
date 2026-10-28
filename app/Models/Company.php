<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected $casts = [
        "is_active" => "boolean",
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, "company_user")
            ->withPivot("role_id", "status", "is_primary")
            ->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
