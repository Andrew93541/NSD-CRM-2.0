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
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
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

    /**
     * Manager roles and above that support/require Two-Factor Authentication.
     */
    public function isManagerOrAbove(?int $companyId = null): bool
    {
        $managerSlugs = [
            'super_admin',
            'company_admin',
            'senior_manager',
            'site_manager',
            'warehouse_manager',
        ];

        $role = $this->currentRole($companyId);
        if ($role) {
            return in_array($role->slug, $managerSlugs, true);
        }

        // Fallback: inspect primary active company membership
        $membership = $this->companies()
            ->wherePivot('status', 'active')
            ->where('companies.is_active', true)
            ->orderByDesc('company_user.is_primary')
            ->first();

        if (!$membership || !($membership->pivot->role_id ?? null)) {
            return false;
        }

        $fallbackRole = Role::find($membership->pivot->role_id);

        return $fallbackRole && in_array($fallbackRole->slug, $managerSlugs, true);
    }

    /**
     * Determine whether an interactive login for this user requires a 2FA OTP challenge.
     */
    public function requiresTwoFactorChallenge(?int $companyId = null): bool
    {
        if ((bool) $this->two_factor_enabled) {
            return true;
        }

        if ((bool) config('auth.enforce_manager_2fa', false) && $this->isManagerOrAbove($companyId)) {
            return true;
        }

        return false;
    }

    /**
     * Generate, hash, and store a 6-digit Two-Factor Authentication OTP valid for 10 minutes.
     */
    public function issueTwoFactorCode(): string
    {
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'two_factor_code' => \Illuminate\Support\Facades\Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
            'two_factor_verified_at' => null,
        ])->save();

        return $code;
    }

    /**
     * Verify a submitted 6-digit Two-Factor Authentication OTP code.
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_code || !$this->two_factor_expires_at) {
            return false;
        }

        if ($this->two_factor_expires_at->isPast()) {
            return false;
        }

        if (!\Illuminate\Support\Facades\Hash::check(trim($code), $this->two_factor_code)) {
            return false;
        }

        $this->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_verified_at' => now(),
        ])->save();

        return true;
    }
}
