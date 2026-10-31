<?php

namespace App\Models;

use App\Services\Security\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role_id', 'status', 'is_primary')
            ->withTimestamps();
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'user_site')
            ->withPivot('role_in_site')
            ->withTimestamps();
    }

    public function siteAssignments(): HasMany
    {
        return $this->hasMany(SiteAssignment::class);
    }

    public function currentCompany(): ?Company
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId) {
            return null;
        }

        return $this->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('status', 'active')
            ->where('companies.is_active', true)
            ->first();
    }

    public function currentRole(?int $companyId = null): ?Role
    {
        $targetCompanyId = $companyId ?? TenantContext::getCompanyId();
        if (!$targetCompanyId) {
            return null;
        }

        $company = $this->companies()
            ->where('companies.id', $targetCompanyId)
            ->wherePivot('status', 'active')
            ->where('companies.is_active', true)
            ->first();

        if (!$company) {
            return null;
        }

        $roleId = $company->pivot->role_id ?? null;
        if (!$roleId) {
            return null;
        }

        return Role::find($roleId);
    }

    public function hasRole(string|array $roleSlugs): bool
    {
        $role = $this->currentRole();
        if (!$role) {
            return false;
        }

        $slugs = is_array($roleSlugs) ? $roleSlugs : [$roleSlugs];

        // Normalize supervisor / site_supervisor synonyms
        if (in_array('supervisor', $slugs, true) || in_array('site_supervisor', $slugs, true)) {
            $slugs[] = 'supervisor';
            $slugs[] = 'site_supervisor';
            $slugs = array_unique($slugs);
        }

        if (in_array('super_admin', $slugs, true) && $role->slug === 'super_admin') {
            return true;
        }

        return in_array($role->slug, $slugs, true);
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->currentRole();
        if (!$role) {
            return false;
        }

        return $role->hasPermission($permission);
    }

    public function isAssignedToSite(int|Site $site): bool
    {
        $currentCompanyId = TenantContext::getCompanyId();
        if (!$currentCompanyId) {
            return false;
        }

        $targetSite = $site instanceof Site ? $site : Site::withoutGlobalScopes()->find($site);
        if (!$targetSite || (int) $targetSite->company_id !== (int) $currentCompanyId) {
            return false;
        }

        // Company admins, super admins, and senior managers have organization-wide site access within their company
        if ($this->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            return true;
        }

        // Direct supervisor or site manager assignment on site within the current company
        if ((int) $targetSite->supervisor_id === (int) $this->id) {
            return true;
        }

        if (isset($targetSite->attributes['site_manager_id']) && (int) $targetSite->attributes['site_manager_id'] === (int) $this->id) {
            return true;
        }

        // Explicit user_site assignment within the current company
        if ($this->sites()->where('sites.id', $targetSite->id)->where('sites.company_id', $currentCompanyId)->exists()) {
            return true;
        }

        // Active dated site_assignments entry
        if (Schema::hasTable('site_assignments')) {
            return SiteAssignment::withoutGlobalScopes()
                ->where('company_id', $currentCompanyId)
                ->where('site_id', $targetSite->id)
                ->where('user_id', $this->id)
                ->where('is_active', true)
                ->exists();
        }

        return false;
    }

    /**
     * Return all site IDs in the current company that this user is authorized to access.
     */
    public function accessibleSiteIds(): array
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId) {
            return [];
        }

        $sites = Site::where('company_id', $companyId)->get();
        if ($this->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            return $sites->pluck('id')->all();
        }

        return $sites
            ->filter(fn (Site $site) => $this->isAssignedToSite($site))
            ->pluck('id')
            ->values()
            ->all();
    }
}
