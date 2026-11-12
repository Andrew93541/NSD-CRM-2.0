<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\User;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    protected function requireCompanyId(): int
    {
        $companyId = TenantContext::getCompanyId();
        if (! $companyId) {
            abort(403, 'Active company context is required.');
        }

        return (int) $companyId;
    }

    protected function authorizeAdmin(string $permission = 'users.view'): User
    {
        /** @var User|null $actor */
        $actor = Auth::user();
        if (! $actor) {
            abort(403, 'Authentication required.');
        }

        if (! $actor->hasRole(['super_admin', 'company_admin']) && ! $actor->hasPermission($permission)) {
            abort(403, 'Only Company Administrators can access User & Access Management.');
        }

        return $actor;
    }

    protected function ensureUserBelongsToCompany(User $target, int $companyId): object
    {
        $pivot = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $target->id)
            ->first();

        if (! $pivot) {
            abort(403, 'Target user does not belong to your active company.');
        }

        return $pivot;
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin('users.view');
        $companyId = $this->requireCompanyId();

        $query = User::query()
            ->select('users.*')
            ->join('company_user', function ($join) use ($companyId) {
                $join->on('company_user.user_id', '=', 'users.id')
                    ->where('company_user.company_id', '=', $companyId);
            })
            ->with([
                'companies' => fn ($q) => $q->where('companies.id', $companyId),
                'sites' => fn ($q) => $q->where('sites.company_id', $companyId)->orderBy('code'),
            ]);

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term);
            });
        }

        if ($request->filled('role_id')) {
            $query->where('company_user.role_id', (int) $request->query('role_id'));
        }

        if ($request->filled('site_id')) {
            $siteId = (int) $request->query('site_id');
            $query->whereHas('sites', fn ($q) => $q->where('sites.id', $siteId)->where('sites.company_id', $companyId));
        }

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if ($status === 'active') {
                $query->where('users.is_active', true)->where('company_user.status', 'active');
            } elseif ($status === 'inactive') {
                $query->where(function ($q) {
                    $q->where('users.is_active', false)->orWhere('company_user.status', '!=', 'active');
                });
            }
        }

        $users = $query->orderBy('users.name')->paginate(15)->withQueryString();
        $roles = Role::where('company_id', $companyId)->orderBy('name')->get()->keyBy('id');
        $sites = Site::where('company_id', $companyId)->orderBy('code')->get();

        $search = (string) $request->query('search', '');
        $roleId = $request->query('role_id');
        $siteId = $request->query('site_id');
        $status = (string) $request->query('status', '');

        $allCompanyUsers = User::query()
            ->select('users.*', 'company_user.status as company_status')
            ->join('company_user', function ($join) use ($companyId) {
                $join->on('company_user.user_id', '=', 'users.id')
                    ->where('company_user.company_id', '=', $companyId);
            })
            ->get();

        $activeCount = $allCompanyUsers->filter(fn ($u) => (bool) $u->is_active && ($u->company_status ?? 'active') === 'active')->count();
        $stats = [
            'total' => $allCompanyUsers->count(),
            'active' => $activeCount,
            'inactive' => max(0, $allCompanyUsers->count() - $activeCount),
            'two_factor' => $allCompanyUsers->filter(fn ($u) => $u->requiresTwoFactor($companyId))->count(),
        ];

        return view('admin.users.index', compact(
            'users',
            'roles',
            'sites',
            'stats',
            'search',
            'roleId',
            'siteId',
            'status',
            'companyId'
        ));
    }

    public function create(): View
    {
        $this->authorizeAdmin('users.create');
        $companyId = $this->requireCompanyId();

        $roles = Role::where('company_id', $companyId)
            ->when(Schema::hasColumn('roles', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
        $sites = Site::where('company_id', $companyId)->orderBy('code')->get();

        return view('admin.users.create', compact('roles', 'sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->authorizeAdmin('users.create');
        $companyId = $this->requireCompanyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password_mode' => ['nullable', 'in:manual,generate'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'restrict_to_assigned_sites' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
            'two_factor_enabled' => ['nullable', 'boolean'],
        ]);

        $role = Role::where('company_id', $companyId)->findOrFail((int) $validated['role_id']);
        if (isset($role->is_active) && ! $role->is_active) {
            return back()->withInput()->with('error', __('Cannot assign an inactive role.'));
        }

        $generatePassword = ($validated['password_mode'] ?? 'manual') === 'generate' || empty($validated['password']);
        $plainPassword = $generatePassword ? Str::password(16) : (string) $validated['password'];

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;
        $mustChangePassword = $request->has('must_change_password') ? $request->boolean('must_change_password') : true;
        $twoFactorEnabled = $request->boolean('two_factor_enabled');
        $restrictSites = $request->boolean('restrict_to_assigned_sites');
        $siteIds = array_values(array_unique(array_map('intval', $validated['site_ids'] ?? [])));

        $user = DB::transaction(function () use (
            $validated,
            $plainPassword,
            $isActive,
            $mustChangePassword,
            $twoFactorEnabled,
            $companyId,
            $role,
            $restrictSites,
            $siteIds,
            $actor
        ) {
            $userPayload = [
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($plainPassword),
                'is_active' => $isActive,
                'two_factor_enabled' => $twoFactorEnabled,
            ];

            if (Schema::hasColumn('users', 'must_change_password')) {
                $userPayload['must_change_password'] = $mustChangePassword;
            }

            $newUser = User::create($userPayload);

            if ($twoFactorEnabled) {
                $newUser->ensureTotpSecret();
            }

            $pivotPayload = [
                'role_id' => $role->id,
                'status' => $isActive ? 'active' : 'inactive',
                'is_primary' => true,
            ];

            if (Schema::hasColumn('company_user', 'restrict_to_assigned_sites')) {
                $pivotPayload['restrict_to_assigned_sites'] = $restrictSites;
            }

            $newUser->companies()->syncWithoutDetaching([
                $companyId => $pivotPayload,
            ]);

            $this->syncCompanySitesForUser($newUser, $companyId, $siteIds, $role->slug, $actor->id);

            return $newUser;
        });

        AuditLog::record(
            'user.created',
            $user,
            null,
            [
                'email' => $user->email,
                'name' => $user->name,
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'site_ids' => $siteIds,
                'restrict_to_assigned_sites' => $restrictSites,
                'is_active' => $isActive,
                'must_change_password' => $mustChangePassword,
                'two_factor_enabled' => $twoFactorEnabled,
            ],
            "Created employee account {$user->email} with role {$role->name}",
            $actor->id,
            $companyId
        );

        if ($generatePassword) {
            session()->flash('generated_temp_password', $plainPassword);
            session()->flash('generated_temp_password_email', $user->email);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('User account :email created successfully.', ['email' => $user->email]));
    }

    public function show(User $user): View
    {
        $this->authorizeAdmin('users.view');
        $companyId = $this->requireCompanyId();
        $pivot = $this->ensureUserBelongsToCompany($user, $companyId);

        $role = $user->roleInCompany($companyId);
        $currentRole = $role;
        $targetUser = $user;
        $roles = Role::where('company_id', $companyId)->orderBy('name')->get();
        $companySites = Site::where('company_id', $companyId)->orderBy('code')->get();
        $sites = $companySites;
        $assignedSiteIds = $user->sites()->where('sites.company_id', $companyId)->pluck('sites.id')->map(fn ($id) => (int) $id)->all();
        $modules = PermissionCatalog::modules();
        $overrides = $user->permissionOverrides($companyId);
        $restrictToAssignedSites = $user->isRestrictedToAssignedSites($companyId);
        $restrictSites = $restrictToAssignedSites;
        $isActive = $user->isActiveInCompany($companyId);
        $isLastAdmin = $user->isLastActiveCompanyAdmin($companyId);

        $permissionStates = [];
        foreach (PermissionCatalog::allPermissionKeys() as $permKey) {
            $permissionStates[$permKey] = $user->permissionState($permKey, $companyId);
        }

        $auditLogs = AuditLog::where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($sq) use ($user) {
                        $sq->where('subject_type', User::class)
                            ->where('subject_id', $user->id);
                    });
            })
            ->with('actor')
            ->latest('id')
            ->limit(40)
            ->get();
        $recentAuditLogs = $auditLogs;

        return view('admin.users.show', compact(
            'user',
            'targetUser',
            'pivot',
            'role',
            'currentRole',
            'roles',
            'companySites',
            'sites',
            'assignedSiteIds',
            'modules',
            'overrides',
            'permissionStates',
            'restrictToAssignedSites',
            'restrictSites',
            'isActive',
            'isLastAdmin',
            'auditLogs',
            'recentAuditLogs'
        ));
    }

    public function edit(User $user): View
    {
        $this->authorizeAdmin('users.edit');
        $companyId = $this->requireCompanyId();
        $pivot = $this->ensureUserBelongsToCompany($user, $companyId);

        $role = $user->roleInCompany($companyId);
        $currentRole = $role;
        $targetUser = $user;
        $roles = Role::where('company_id', $companyId)->orderBy('name')->get();
        $sites = Site::where('company_id', $companyId)->orderBy('code')->get();
        $assignedSiteIds = $user->sites()->where('sites.company_id', $companyId)->pluck('sites.id')->map(fn ($id) => (int) $id)->all();
        $restrictToAssignedSites = $user->isRestrictedToAssignedSites($companyId);
        $restrictSites = $restrictToAssignedSites;
        $isActive = $user->isActiveInCompany($companyId);
        $modules = PermissionCatalog::modules();
        $overrides = $user->permissionOverrides($companyId);

        return view('admin.users.edit', compact(
            'user',
            'targetUser',
            'pivot',
            'role',
            'currentRole',
            'roles',
            'sites',
            'assignedSiteIds',
            'restrictToAssignedSites',
            'restrictSites',
            'isActive',
            'modules',
            'overrides'
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAdmin('users.edit');
        $companyId = $this->requireCompanyId();
        $pivot = $this->ensureUserBelongsToCompany($user, $companyId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'restrict_to_assigned_sites' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        $newRole = Role::where('company_id', $companyId)->findOrFail((int) $validated['role_id']);
        $oldRole = $user->currentRole($companyId);
        $newActive = $request->has('is_active') ? $request->boolean('is_active') : (bool) $user->is_active;

        // Protect self-deactivation and last active Company Admin
        if ((int) $actor->id === (int) $user->id && ! $newActive) {
            return back()->withInput()->with('error', __('You cannot deactivate your own account.'));
        }

        $demotingFromAdmin = $oldRole && $oldRole->isProtectedAdminRole() && ! $newRole->isProtectedAdminRole();
        if (($demotingFromAdmin || ! $newActive) && $user->isLastActiveCompanyAdmin($companyId)) {
            return back()->withInput()->with('error', __('Cannot deactivate or remove administrative role from the last active Company Administrator.'));
        }

        if ((int) $actor->id === (int) $user->id && $demotingFromAdmin) {
            return back()->withInput()->with('error', __('You cannot remove your own Company Administrator role.'));
        }

        $restrictSites = $request->boolean('restrict_to_assigned_sites');
        $siteIds = array_values(array_unique(array_map('intval', $validated['site_ids'] ?? [])));
        $oldSiteIds = $user->sites()->where('sites.company_id', $companyId)->pluck('sites.id')->map(fn ($id) => (int) $id)->all();

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $oldRole?->id,
            'role_slug' => $oldRole?->slug,
            'is_active' => (bool) $user->is_active,
            'site_ids' => $oldSiteIds,
        ];

        DB::transaction(function () use (
            $user,
            $validated,
            $newActive,
            $request,
            $companyId,
            $newRole,
            $restrictSites,
            $siteIds,
            $actor
        ) {
            $userUpdates = [
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'is_active' => $newActive,
            ];

            if (Schema::hasColumn('users', 'must_change_password') && $request->has('must_change_password')) {
                $userUpdates['must_change_password'] = $request->boolean('must_change_password');
            }

            $user->update($userUpdates);

            $pivotUpdates = [
                'role_id' => $newRole->id,
                'status' => $newActive ? 'active' : 'inactive',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('company_user', 'restrict_to_assigned_sites')) {
                $pivotUpdates['restrict_to_assigned_sites'] = $restrictSites;
            }

            DB::table('company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->update($pivotUpdates);

            $this->syncCompanySitesForUser($user, $companyId, $siteIds, $newRole->slug, $actor->id);

            if (! $newActive && Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        AuditLog::record(
            'user.updated',
            $user,
            $oldValues,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $newRole->id,
                'role_slug' => $newRole->slug,
                'is_active' => $newActive,
                'site_ids' => $siteIds,
                'restrict_to_assigned_sites' => $restrictSites,
            ],
            "Updated user {$user->email}",
            $actor->id,
            $companyId
        );

        if ((int) ($oldRole?->id ?? 0) !== (int) $newRole->id) {
            AuditLog::record(
                'user.role_changed',
                $user,
                ['role_id' => $oldRole?->id, 'role_slug' => $oldRole?->slug],
                ['role_id' => $newRole->id, 'role_slug' => $newRole->slug],
                "Role changed to {$newRole->name}",
                $actor->id,
                $companyId
            );
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('User account :name updated successfully.', ['name' => $user->name]));
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAdmin('users.manage');
        $companyId = $this->requireCompanyId();
        $this->ensureUserBelongsToCompany($user, $companyId);

        if (! Schema::hasColumn('company_user', 'permission_overrides')) {
            return back()->with('error', __('Permission override column is not available until migrations are run.'));
        }

        $submitted = (array) $request->input('overrides', []);
        $role = $user->currentRole($companyId);
        if ($role && $role->isProtectedAdminRole()) {
            $nonAttendanceOverrides = array_filter(
                array_keys($submitted),
                fn ($k) => is_string($k) && ! str_starts_with($k, 'attendance.') && in_array($submitted[$k] ?? '', ['allow', 'deny'], true)
            );
            if (! empty($nonAttendanceOverrides)) {
                return back()->with('error', __('Company Administrator core permissions are system-protected and cannot be overridden.'));
            }
        }

        $validKeys = PermissionCatalog::assignableCustomRolePermissionKeys();
        $oldOverrides = $user->permissionOverrides($companyId);

        $newOverrides = [];
        foreach ($submitted as $permKey => $mode) {
            if (! is_string($permKey) || ! in_array($permKey, $validKeys, true)) {
                continue;
            }
            if (in_array($mode, ['allow', 'deny'], true)) {
                $newOverrides[$permKey] = $mode;
            }
        }

        DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->update([
                'permission_overrides' => empty($newOverrides) ? null : json_encode($newOverrides),
                'updated_at' => now(),
            ]);

        $granted = array_keys(array_filter($newOverrides, fn ($m) => $m === 'allow'));
        $denied = array_keys(array_filter($newOverrides, fn ($m) => $m === 'deny'));

        AuditLog::record(
            'user.permissions_updated',
            $user,
            ['overrides' => $oldOverrides],
            [
                'overrides' => $newOverrides,
                'explicit_allow' => $granted,
                'explicit_deny' => $denied,
            ],
            "Updated individual permission overrides for {$user->email}",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('Individual permission overrides saved for :name.', ['name' => $user->name]));
    }

    public function resetPermissions(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAdmin('users.manage');
        $companyId = $this->requireCompanyId();
        $this->ensureUserBelongsToCompany($user, $companyId);

        $oldOverrides = $user->permissionOverrides($companyId);

        if (Schema::hasColumn('company_user', 'permission_overrides')) {
            DB::table('company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->update([
                    'permission_overrides' => null,
                    'updated_at' => now(),
                ]);
        }

        AuditLog::record(
            'user.permissions_reset',
            $user,
            ['overrides' => $oldOverrides],
            ['overrides' => []],
            "Reset individual permissions to role defaults for {$user->email}",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('Permissions for :name have been reset to role defaults.', ['name' => $user->name]));
    }

    public function updateSites(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAdmin('users.edit');
        $companyId = $this->requireCompanyId();
        $this->ensureUserBelongsToCompany($user, $companyId);

        $validated = $request->validate([
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'restrict_to_assigned_sites' => ['nullable', 'boolean'],
        ]);

        $siteIds = array_values(array_unique(array_map('intval', $validated['site_ids'] ?? [])));
        $restrictSites = $request->boolean('restrict_to_assigned_sites');
        $oldSiteIds = $user->sites()->where('sites.company_id', $companyId)->pluck('sites.id')->map(fn ($id) => (int) $id)->all();
        $role = $user->currentRole($companyId);

        DB::transaction(function () use ($user, $companyId, $siteIds, $restrictSites, $role, $actor) {
            if (Schema::hasColumn('company_user', 'restrict_to_assigned_sites')) {
                DB::table('company_user')
                    ->where('company_id', $companyId)
                    ->where('user_id', $user->id)
                    ->update([
                        'restrict_to_assigned_sites' => $restrictSites,
                        'updated_at' => now(),
                    ]);
            }

            $this->syncCompanySitesForUser($user, $companyId, $siteIds, $role?->slug ?? 'staff', $actor->id);
        });

        AuditLog::record(
            'user.sites_updated',
            $user,
            ['site_ids' => $oldSiteIds],
            ['site_ids' => $siteIds, 'restrict_to_assigned_sites' => $restrictSites],
            "Updated site assignments for {$user->email}",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('Site access assignments updated for :name.', ['name' => $user->name]));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAdmin('users.manage');
        $companyId = $this->requireCompanyId();
        $this->ensureUserBelongsToCompany($user, $companyId);

        $validated = $request->validate([
            'password_mode' => ['nullable', 'in:manual,generate'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'must_change_password' => ['nullable', 'boolean'],
        ]);

        $generatePassword = ($validated['password_mode'] ?? 'generate') === 'generate' || empty($validated['password']);
        $plainPassword = $generatePassword ? Str::password(16) : (string) $validated['password'];
        $mustChange = $request->has('must_change_password') ? $request->boolean('must_change_password') : true;

        $updates = [
            'password' => Hash::make($plainPassword),
        ];

        if (Schema::hasColumn('users', 'must_change_password')) {
            $updates['must_change_password'] = $mustChange;
        }
        if (Schema::hasColumn('users', 'password_changed_at')) {
            $updates['password_changed_at'] = now();
        }

        $user->forceFill($updates)->save();

        // Revoke existing sessions for target user (except current session if resetting own password)
        if (Schema::hasTable('sessions')) {
            $sessionQuery = DB::table('sessions')->where('user_id', $user->id);
            if ((int) $actor->id === (int) $user->id) {
                $sessionQuery->where('id', '!=', $request->session()->getId());
            }
            $sessionQuery->delete();
        }

        AuditLog::record(
            'user.password_reset_by_admin',
            $user,
            null,
            [
                'target_email' => $user->email,
                'must_change_password' => $mustChange,
                'sessions_revoked' => true,
            ],
            "Administrator reset password for {$user->email}",
            $actor->id,
            $companyId
        );

        if ($generatePassword) {
            session()->flash('generated_temp_password', $plainPassword);
            session()->flash('generated_temp_password_email', $user->email);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('Password has been securely reset for :name and active sessions have been revoked.', ['name' => $user->name]));
    }

    public function showPasswordChange(): View
    {
        return view('admin.users.password-change', [
            'user' => Auth::user(),
        ]);
    }

    public function updateOwnPassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed', 'different:current_password'],
        ]);

        $updates = [
            'password' => Hash::make($validated['password']),
        ];

        if (Schema::hasColumn('users', 'must_change_password')) {
            $updates['must_change_password'] = false;
        }
        if (Schema::hasColumn('users', 'password_changed_at')) {
            $updates['password_changed_at'] = now();
        }

        $user->forceFill($updates)->save();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        AuditLog::record(
            'user.password_changed',
            $user,
            null,
            ['email' => $user->email],
            'User updated their account password',
            $user->id,
            TenantContext::getCompanyId()
        );

        return redirect()
            ->route('dashboard')
            ->with('success', __('Your password has been updated successfully.'));
    }

    /**
     * Synchronize a user's assigned sites within a specific company without disturbing memberships in other companies.
     *
     * @param  array<int, int>  $siteIds
     */
    protected function syncCompanySitesForUser(User $user, int $companyId, array $siteIds, string $roleSlug, int $actorId): void
    {
        $companySiteIds = Site::where('company_id', $companyId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validTargetSiteIds = array_values(array_intersect($siteIds, $companySiteIds));
        $removedSiteIds = array_values(array_diff($companySiteIds, $validTargetSiteIds));

        if (! empty($removedSiteIds)) {
            $user->sites()->detach($removedSiteIds);

            if (Schema::hasTable('site_assignments')) {
                SiteAssignment::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('user_id', $user->id)
                    ->whereIn('site_id', $removedSiteIds)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'end_date' => now()->toDateString(),
                    ]);
            }
        }

        $sitesToAttach = Site::where('company_id', $companyId)->whereIn('id', $validTargetSiteIds)->get();
        foreach ($sitesToAttach as $site) {
            SiteAssignment::recordUserAssignment(
                $site,
                $user,
                $roleSlug,
                now()->toDateString(),
                $actorId,
                'Assigned via User & Access Management',
                false
            );
        }
    }
}
