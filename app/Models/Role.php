<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Services\Security\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ['id'];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'role_id', 'user_id')
            ->withPivot('company_id', 'status', 'is_primary')
            ->withTimestamps();
    }

    public function assignedUsersCount(): int
    {
        return (int) DB::table('company_user')
            ->where('company_id', $this->company_id)
            ->where('role_id', $this->id)
            ->count();
    }

    public function isSystemRole(): bool
    {
        if ((bool) ($this->attributes['is_system'] ?? false)) {
            return true;
        }

        return in_array($this->slug, PermissionCatalog::SYSTEM_ROLE_SLUGS, true);
    }

    public function isProtectedAdminRole(): bool
    {
        return in_array($this->slug, ['super_admin', 'company_admin'], true);
    }

    /**
     * Return all effective permission keys granted by this role.
     *
     * @return array<int, string>
     */
    public function effectivePermissions(): array
    {
        if (isset($this->attributes['is_active']) && ! (bool) $this->attributes['is_active']) {
            return [];
        }

        $stored = $this->permissions ?? [];
        $cleanStored = array_values(array_filter(
            $stored,
            fn ($p) => is_string($p) && ! in_array($p, ['*', '__customized__'], true)
        ));

        if ($this->isProtectedAdminRole()) {
            return array_values(array_unique(array_merge(
                PermissionCatalog::defaultAdminPermissionKeys(),
                $cleanStored
            )));
        }

        if (! in_array('__customized__', $stored, true)) {
            $defaults = PermissionCatalog::defaultRolePermissions()[$this->slug] ?? [];

            return array_values(array_unique(array_merge($cleanStored, $defaults)));
        }

        return $cleanStored;
    }

    public function hasPermission(string $permission): bool
    {
        if (isset($this->attributes['is_active']) && ! (bool) $this->attributes['is_active']) {
            return false;
        }

        return PermissionCatalog::roleHasPermission($this->slug, $this->permissions, $permission);
    }
}
