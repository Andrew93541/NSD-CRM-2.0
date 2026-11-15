<?php

namespace App\Models;

use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use App\Services\Security\TotpAuthenticatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'must_change_password',
        'password_changed_at',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_verified_at',
        'two_factor_last_step',
        'last_login_at',
        'last_login_ip',
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
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
            'two_factor_last_step' => 'integer',
            'last_login_at' => 'datetime',
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

    protected array $memoCompanyContext = [];

    public function refresh(): static
    {
        $this->memoCompanyContext = [];

        return parent::refresh();
    }

    public function unsetRelations(): static
    {
        $this->memoCompanyContext = [];

        return parent::unsetRelations();
    }

    protected function memoCacheKey(int $companyId, string $slot): string
    {
        $reqId = app()->bound('request') ? spl_object_id(request()) : 0;

        return "{$reqId}:{$companyId}:{$slot}";
    }

    public function currentCompany(): ?Company
    {
        $companyId = TenantContext::getCompanyId();
        if (! $companyId) {
            return null;
        }

        $key = $this->memoCacheKey((int) $companyId, 'company');
        if (array_key_exists($key, $this->memoCompanyContext)) {
            return $this->memoCompanyContext[$key];
        }

        $company = $this->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('status', 'active')
            ->where('companies.is_active', true)
            ->first();

        $this->memoCompanyContext[$key] = $company;

        return $company;
    }

    public function currentRole(?int $companyId = null): ?Role
    {
        $targetCompanyId = $companyId ?? TenantContext::getCompanyId();
        if (! $targetCompanyId) {
            return null;
        }

        $key = $this->memoCacheKey((int) $targetCompanyId, 'role');
        if (array_key_exists($key, $this->memoCompanyContext)) {
            return $this->memoCompanyContext[$key];
        }

        $compKey = $this->memoCacheKey((int) $targetCompanyId, 'company');
        if (array_key_exists($compKey, $this->memoCompanyContext)) {
            $company = $this->memoCompanyContext[$compKey];
        } else {
            $company = $this->companies()
                ->where('companies.id', $targetCompanyId)
                ->wherePivot('status', 'active')
                ->where('companies.is_active', true)
                ->first();
            $this->memoCompanyContext[$compKey] = $company;
        }

        if (! $company) {
            $this->memoCompanyContext[$key] = null;

            return null;
        }

        $roleId = $company->pivot->role_id ?? null;
        $role = $roleId ? Role::find($roleId) : null;

        $this->memoCompanyContext[$key] = $role;

        return $role;
    }

    /**
     * Return the user's assigned Role in the given or current company (regardless of active/inactive status).
     */
    public function roleInCompany(?int $companyId = null): ?Role
    {
        $pivot = $this->companyPivotRow($companyId);
        if (! $pivot || empty($pivot->role_id)) {
            return null;
        }

        return Role::find((int) $pivot->role_id);
    }

    /**
     * Determine whether the user is active both globally and within the given/current company.
     */
    public function isActiveInCompany(?int $companyId = null): bool
    {
        if (isset($this->is_active) && ! (bool) $this->is_active) {
            return false;
        }

        $pivot = $this->companyPivotRow($companyId);
        if (! $pivot) {
            return false;
        }

        return ($pivot->status ?? 'active') === 'active';
    }

    public function hasRole(string|array $roleSlugs): bool
    {
        $role = $this->currentRole();
        if (! $role) {
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

    /**
     * Return the user's company_user row for the given or current company.
     */
    public function companyPivotRow(?int $companyId = null): ?object
    {
        $targetCompanyId = $companyId ?? TenantContext::getCompanyId();
        if (! $targetCompanyId) {
            return null;
        }

        $key = $this->memoCacheKey((int) $targetCompanyId, 'pivot');
        if (array_key_exists($key, $this->memoCompanyContext)) {
            return $this->memoCompanyContext[$key];
        }

        $row = DB::table('company_user')
            ->where('company_id', $targetCompanyId)
            ->where('user_id', $this->id)
            ->first();

        $this->memoCompanyContext[$key] = $row;

        return $row;
    }

    /**
     * Return individual permission overrides ['perm.key' => 'allow'|'deny'] for the current/given company.
     *
     * @return array<string, string>
     */
    public function permissionOverrides(?int $companyId = null): array
    {
        $row = $this->companyPivotRow($companyId);
        if (! $row || ! property_exists($row, 'permission_overrides') || empty($row->permission_overrides)) {
            return [];
        }

        $decoded = is_string($row->permission_overrides)
            ? json_decode($row->permission_overrides, true)
            : (array) $row->permission_overrides;

        if (! is_array($decoded)) {
            return [];
        }

        $clean = [];
        foreach ($decoded as $key => $mode) {
            if (is_string($key) && in_array($mode, ['allow', 'deny'], true)) {
                $clean[$key] = $mode;
            }
        }

        return $clean;
    }

    public function permissionOverrideFor(string $permission, ?int $companyId = null): ?string
    {
        $overrides = $this->permissionOverrides($companyId);
        if (empty($overrides)) {
            return null;
        }

        $aliases = PermissionCatalog::expandAliases($permission);

        // Explicit deny on the exact key or any equivalent alias always wins first
        foreach ($aliases as $key) {
            if (($overrides[$key] ?? null) === 'deny') {
                return 'deny';
            }
        }

        foreach ($aliases as $key) {
            if (($overrides[$key] ?? null) === 'allow') {
                return 'allow';
            }
        }

        return null;
    }

    public function isExplicitlyDenied(string $permission, ?int $companyId = null): bool
    {
        return $this->permissionOverrideFor($permission, $companyId) === 'deny';
    }

    /**
     * Evaluate effective permission with strict precedence:
     * 1. Explicit user deny override (including aliases) -> false
     * 2. Explicit user allow override (including aliases) -> true
     * 3. Inherited role permission -> role->hasPermission($permission)
     */
    public function hasPermission(string $permission, ?int $companyId = null): bool
    {
        $role = $this->currentRole($companyId);
        if (! $role) {
            return false;
        }

        $override = $this->permissionOverrideFor($permission, $companyId);
        if ($override === 'deny') {
            return false;
        }

        if ($override === 'allow') {
            return true;
        }

        return $role->hasPermission($permission);
    }

    /**
     * Unified helper for policies and controllers that checks effective permission first
     * (honoring explicit user deny overrides and custom/customized role matrices) before
     * falling back to predefined role slugs for uncustomized system roles.
     */
    public function canPerform(string $permission, string|array $fallbackRoles = [], ?int $companyId = null): bool
    {
        if ($this->isExplicitlyDenied($permission, $companyId)) {
            return false;
        }

        if ($this->hasPermission($permission, $companyId)) {
            return true;
        }

        if (in_array($permission, PermissionCatalog::WILDCARD_EXCLUDED_ATTENDANCE_PERMISSIONS, true)) {
            return false;
        }

        $role = $this->currentRole($companyId);
        if (! $role) {
            return false;
        }

        if (array_key_exists('is_active', $role->getAttributes()) && ! (bool) $role->is_active) {
            return false;
        }

        // Custom roles and explicitly customized system roles must strictly obey their configured permissions matrix.
        if (! $role->isSystemRole() || in_array('__customized__', $role->permissions ?? [], true)) {
            return false;
        }

        if (! empty($fallbackRoles)) {
            return $this->hasRole($fallbackRoles);
        }

        return false;
    }

    /**
     * Inspect detailed permission state for UI matrices:
     * returns ['effective' => bool, 'source' => 'system_admin'|'explicit_allow'|'explicit_deny'|'inherited_allow'|'inherited_deny', 'inherited' => bool, 'override' => ?string]
     *
     * @return array{effective: bool, source: string, inherited: bool, override: ?string}
     */
    public function permissionState(string $permission, ?int $companyId = null): array
    {
        $role = $this->currentRole($companyId);
        if (! $role) {
            return [
                'effective' => false,
                'source' => 'inherited_deny',
                'inherited' => false,
                'override' => null,
            ];
        }

        $inherited = $role->hasPermission($permission);
        $override = $this->permissionOverrideFor($permission, $companyId);

        if ($override === 'deny') {
            return [
                'effective' => false,
                'source' => 'explicit_deny',
                'inherited' => $inherited,
                'override' => 'deny',
            ];
        }

        if ($override === 'allow') {
            return [
                'effective' => true,
                'source' => 'explicit_allow',
                'inherited' => $inherited,
                'override' => 'allow',
            ];
        }

        if ($role->isProtectedAdminRole() && $inherited) {
            return [
                'effective' => true,
                'source' => 'system_admin',
                'inherited' => true,
                'override' => null,
            ];
        }

        return [
            'effective' => $inherited,
            'source' => $inherited ? 'inherited_allow' : 'inherited_deny',
            'inherited' => $inherited,
            'override' => null,
        ];
    }

    public function isRestrictedToAssignedSites(?int $companyId = null): bool
    {
        $role = $this->currentRole($companyId);
        if ($role && $role->isProtectedAdminRole()) {
            return false;
        }

        $row = $this->companyPivotRow($companyId);
        if ($row && property_exists($row, 'restrict_to_assigned_sites') && (bool) $row->restrict_to_assigned_sites) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether this user is the last active Company Administrator in the given company.
     */
    public function isLastActiveCompanyAdmin(int $companyId): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $role = $this->currentRole($companyId);
        if (! $role || ! in_array($role->slug, ['company_admin', 'super_admin'], true)) {
            return false;
        }

        $adminRoleIds = Role::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('slug', ['company_admin', 'super_admin'])
            ->pluck('id')
            ->all();

        if (empty($adminRoleIds)) {
            return false;
        }

        $otherActiveAdminsCount = DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->where('company_user.company_id', $companyId)
            ->whereIn('company_user.role_id', $adminRoleIds)
            ->where('company_user.status', 'active')
            ->where('users.is_active', true)
            ->where('users.id', '!=', $this->id)
            ->count();

        return $otherActiveAdminsCount === 0;
    }

    public function isAssignedToSite(int|Site $site): bool
    {
        $currentCompanyId = TenantContext::getCompanyId();
        if (! $currentCompanyId) {
            return false;
        }

        if (app()->bound('request') && request()->isMethodSafe()) {
            $siteId = $site instanceof Site ? (int) $site->id : (int) $site;

            return in_array($siteId, $this->accessibleSiteIds(), true);
        }

        $targetSite = $site instanceof Site ? $site : Site::withoutGlobalScopes()->find($site);
        if (! $targetSite || (int) $targetSite->company_id !== (int) $currentCompanyId) {
            return false;
        }

        // Company admins and super admins always have organization-wide site access within their company
        if ($this->hasRole(['super_admin', 'company_admin'])) {
            return true;
        }

        // Senior managers have organization-wide access unless explicitly restricted to assigned sites
        if ($this->hasRole('senior_manager') && ! $this->isRestrictedToAssignedSites($currentCompanyId)) {
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
        if (! $companyId) {
            return [];
        }

        $isSafeRequest = app()->bound('request') && request()->isMethodSafe();
        $cacheKey = $this->memoCacheKey((int) $companyId, 'accessible_sites');

        if ($isSafeRequest && array_key_exists($cacheKey, $this->memoCompanyContext)) {
            return $this->memoCompanyContext[$cacheKey];
        }

        $sites = Site::where('company_id', $companyId)->get();
        if ($this->hasRole(['super_admin', 'company_admin'])
            || ($this->hasRole('senior_manager') && ! $this->isRestrictedToAssignedSites($companyId))
        ) {
            $ids = $sites->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            if ($isSafeRequest) {
                $this->memoCompanyContext[$cacheKey] = $ids;
            }

            return $ids;
        }

        $userSiteIds = $this->sites()
            ->where('sites.company_id', $companyId)
            ->pluck('sites.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $datedSiteIds = Schema::hasTable('site_assignments')
            ? SiteAssignment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $this->id)
                ->where('is_active', true)
                ->pluck('site_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $explicitLookup = array_flip(array_merge($userSiteIds, $datedSiteIds));

        $ids = $sites
            ->filter(function (Site $site) use ($explicitLookup) {
                if ((int) $site->supervisor_id === (int) $this->id) {
                    return true;
                }
                if (isset($site->attributes['site_manager_id']) && (int) $site->attributes['site_manager_id'] === (int) $this->id) {
                    return true;
                }

                return isset($explicitLookup[(int) $site->id]);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($isSafeRequest) {
            $this->memoCompanyContext[$cacheKey] = $ids;
        }

        return $ids;
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

        if (! $membership || ! ($membership->pivot->role_id ?? null)) {
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

    public function requiresTwoFactor(?int $companyId = null): bool
    {
        return $this->requiresTwoFactorChallenge($companyId);
    }

    /**
     * Ensure an RFC 6238 Base32 TOTP secret is provisioned for this user.
     */
    public function ensureTotpSecret(): string
    {
        if (! empty($this->two_factor_secret) && strlen((string) $this->two_factor_secret) >= 16) {
            return (string) $this->two_factor_secret;
        }

        $secret = app(TotpAuthenticatorService::class)->generateSecret(32);

        $this->forceFill([
            'two_factor_secret' => $secret,
        ])->save();

        return $secret;
    }

    /**
     * Return the standard otpauth://totp/ URI for Authenticator App enrollment.
     */
    public function totpOtpAuthUri(): string
    {
        $secret = $this->ensureTotpSecret();

        return app(TotpAuthenticatorService::class)->buildOtpAuthUri(
            (string) config('app.name', 'NSD CRM 2.0'),
            (string) $this->email,
            $secret
        );
    }

    /**
     * Generate a local inline SVG QR code for the user's otpauth://totp/ URI (zero external requests).
     */
    public function totpQrSvg(int $sizePx = 180): string
    {
        return app(TotpAuthenticatorService::class)->generateQrSvg(
            $this->totpOtpAuthUri(),
            $sizePx
        );
    }

    /**
     * Generate, hash, and store a 6-digit Two-Factor Authentication OTP valid for 10 minutes,
     * while ensuring an RFC 6238 TOTP secret is provisioned.
     * Never writes plaintext OTP codes to application logs.
     */
    public function issueTwoFactorCode(bool $deliverEmailFallback = false): string
    {
        $this->ensureTotpSecret();

        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        if ($deliverEmailFallback && ! empty($this->email) && config('mail.default') !== 'log') {
            try {
                Mail::raw(
                    "Your NSD CRM 2.0 security verification code is: {$code}. This code expires in 10 minutes.",
                    function ($message) {
                        $message->to($this->email)->subject('NSD CRM 2.0 Security Verification Code');
                    }
                );
            } catch (\Throwable) {
                // Fail safely if SMTP is unreachable; user can always verify via RFC 6238 Authenticator App TOTP
            }
        }

        return $code;
    }

    /**
     * Verify a submitted 6-digit Two-Factor Authentication code via RFC 6238 TOTP
     * (primary Authenticator App with replay protection) or hashed email fallback OTP.
     */
    public function verifyTwoFactorCode(string $code, ?int $timestamp = null): bool
    {
        $trimmed = trim($code);
        $totpValid = false;

        if (! empty($this->two_factor_secret)) {
            $totpValid = app(TotpAuthenticatorService::class)
                ->verifyCodeWithReplayProtection(
                    $this->id,
                    (string) $this->two_factor_secret,
                    $trimmed,
                    1,
                    $timestamp
                );
        }

        $otpValid = false;
        if (! $totpValid && $this->two_factor_code && $this->two_factor_expires_at && ! $this->two_factor_expires_at->isPast()) {
            $otpValid = Hash::check($trimmed, $this->two_factor_code);
        }

        if (! $totpValid && ! $otpValid) {
            return false;
        }

        $this->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_verified_at' => now(),
            'two_factor_enabled' => true,
        ])->save();

        return true;
    }

    /**
     * Reset all Two-Factor Authentication secrets and enrollment state for administrator recovery.
     */
    public function resetTwoFactorEnrollment(): void
    {
        app(TotpAuthenticatorService::class)->clearReplayState($this->id);

        $this->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_verified_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }
}
