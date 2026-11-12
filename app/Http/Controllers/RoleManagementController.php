<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleManagementController extends Controller
{
    protected function requireCompanyId(): int
    {
        $companyId = TenantContext::getCompanyId();
        if (! $companyId) {
            abort(403, 'Active company context is required.');
        }

        return (int) $companyId;
    }

    protected function authorizeAdmin(string $permission = 'roles.view'): User
    {
        /** @var User|null $actor */
        $actor = Auth::user();
        if (! $actor) {
            abort(403, 'Authentication required.');
        }

        if (! $actor->hasRole(['super_admin', 'company_admin']) && ! $actor->hasPermission($permission)) {
            abort(403, 'Only Company Administrators can manage roles and permissions.');
        }

        return $actor;
    }

    protected function ensureRoleInCompany(Role $role, int $companyId): void
    {
        if ((int) $role->company_id !== $companyId) {
            abort(403, 'Role does not belong to your active company.');
        }
    }

    public function index(): View
    {
        $this->authorizeAdmin('roles.view');
        $companyId = $this->requireCompanyId();

        $roles = Role::where('company_id', $companyId)
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->get();

        $userCounts = DB::table('company_user')
            ->select('role_id', DB::raw('COUNT(*) as total'))
            ->where('company_id', $companyId)
            ->groupBy('role_id')
            ->pluck('total', 'role_id');
        $userCountsByRole = $userCounts;

        $templates = PermissionCatalog::customRoleTemplates();
        $modules = PermissionCatalog::modules();

        return view('admin.roles.index', compact('roles', 'userCounts', 'userCountsByRole', 'templates', 'modules'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin('roles.create');
        $companyId = $this->requireCompanyId();

        $modules = PermissionCatalog::modules();
        $templates = PermissionCatalog::customRoleTemplates();

        $prefill = [
            'name' => '',
            'slug' => '',
            'description' => '',
            'permissions' => ['dashboard.view'],
        ];

        if ($request->filled('template')) {
            $tplKey = (string) $request->query('template');
            if (isset($templates[$tplKey])) {
                $prefill = $templates[$tplKey];
            }
        } elseif ($request->filled('duplicate_from')) {
            $sourceRole = Role::where('company_id', $companyId)->find((int) $request->query('duplicate_from'));
            if ($sourceRole) {
                $prefill = [
                    'name' => $sourceRole->name . ' (Custom)',
                    'slug' => $sourceRole->slug . '_custom',
                    'description' => (string) $sourceRole->description,
                    'permissions' => array_values(array_intersect(
                        $sourceRole->effectivePermissions(),
                        PermissionCatalog::assignableCustomRolePermissionKeys()
                    )),
                ];
            }
        }

        return view('admin.roles.create', compact('modules', 'templates', 'prefill'));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->authorizeAdmin('roles.create');
        $companyId = $this->requireCompanyId();

        $rawSlug = $request->filled('slug')
            ? Str::slug((string) $request->input('slug'), '_')
            : Str::slug((string) $request->input('name'), '_');

        $request->merge(['slug' => $rawSlug]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::notIn(PermissionCatalog::SYSTEM_ROLE_SLUGS),
                Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'slug.not_in' => 'That role identifier is reserved for a predefined system role.',
        ]);

        // Strip any admin-reserved permissions so a custom role can never escalate to Company Admin
        $allowedKeys = PermissionCatalog::assignableCustomRolePermissionKeys();
        $cleanPerms = array_values(array_unique(array_intersect(
            (array) ($validated['permissions'] ?? []),
            $allowedKeys
        )));

        $storedPerms = array_merge(['__customized__'], $cleanPerms);

        $payload = [
            'company_id' => $companyId,
            'name' => trim($validated['name']),
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'permissions' => $storedPerms,
        ];

        if (Schema::hasColumn('roles', 'is_system')) {
            $payload['is_system'] = false;
        }
        if (Schema::hasColumn('roles', 'is_active')) {
            $payload['is_active'] = true;
        }

        $role = Role::create($payload);

        AuditLog::record(
            'role.created',
            $role,
            null,
            [
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions' => $cleanPerms,
            ],
            "Created custom role {$role->name} ({$role->slug})",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('success', __('Custom role :name created successfully.', ['name' => $role->name]));
    }

    public function edit(Role $role): View
    {
        $this->authorizeAdmin('roles.edit');
        $companyId = $this->requireCompanyId();
        $this->ensureRoleInCompany($role, $companyId);

        $modules = PermissionCatalog::modules();
        $effectivePermissions = $role->effectivePermissions();

        $assignedUsers = User::query()
            ->select('users.*')
            ->join('company_user', 'company_user.user_id', '=', 'users.id')
            ->where('company_user.company_id', $companyId)
            ->where('company_user.role_id', $role->id)
            ->orderBy('users.name')
            ->get();

        $otherRoles = Role::where('company_id', $companyId)
            ->where('id', '!=', $role->id)
            ->orderBy('name')
            ->get();
        $reassignRoles = $otherRoles;
        $assignedCount = $assignedUsers->count();

        return view('admin.roles.edit', compact('role', 'modules', 'effectivePermissions', 'assignedUsers', 'assignedCount', 'otherRoles', 'reassignRoles'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $actor = $this->authorizeAdmin('roles.edit');
        $companyId = $this->requireCompanyId();
        $this->ensureRoleInCompany($role, $companyId);

        if ($role->isProtectedAdminRole()) {
            return back()->with('error', __('System Administrator roles have full system access and cannot be restricted.'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $allowedKeys = PermissionCatalog::assignableCustomRolePermissionKeys();
        $cleanPerms = array_values(array_unique(array_intersect(
            (array) ($validated['permissions'] ?? []),
            $allowedKeys
        )));

        $oldPermissions = $role->effectivePermissions();
        $storedPerms = array_merge(['__customized__'], $cleanPerms);

        $updates = [
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? $role->description,
            'permissions' => $storedPerms,
        ];

        if (! $role->isSystemRole() && Schema::hasColumn('roles', 'is_active') && $request->has('is_active')) {
            $newActive = $request->boolean('is_active');
            if (! $newActive && $role->assignedUsersCount() > 0) {
                return back()->with('error', __('Cannot deactivate a role while users are actively assigned to it. Reassign users first.'));
            }
            $updates['is_active'] = $newActive;
        }

        $role->update($updates);

        AuditLog::record(
            'role.updated',
            $role,
            ['permissions' => $oldPermissions],
            [
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions' => $cleanPerms,
            ],
            "Updated role permissions for {$role->name}",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('success', __('Role :name updated successfully.', ['name' => $role->name]));
    }

    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        $actor = $this->authorizeAdmin('roles.create');
        $companyId = $this->requireCompanyId();
        $this->ensureRoleInCompany($role, $companyId);

        $baseSlug = Str::slug($role->slug . '_copy', '_');
        $candidateSlug = $baseSlug;
        $counter = 2;
        while (
            in_array($candidateSlug, PermissionCatalog::SYSTEM_ROLE_SLUGS, true)
            || Role::where('company_id', $companyId)->where('slug', $candidateSlug)->exists()
        ) {
            $candidateSlug = "{$baseSlug}_{$counter}";
            $counter++;
        }

        $allowedKeys = PermissionCatalog::assignableCustomRolePermissionKeys();
        $clonedPerms = array_values(array_intersect($role->effectivePermissions(), $allowedKeys));

        $payload = [
            'company_id' => $companyId,
            'name' => $role->name . ' (Copy)',
            'slug' => $candidateSlug,
            'description' => $role->description,
            'permissions' => array_merge(['__customized__'], $clonedPerms),
        ];

        if (Schema::hasColumn('roles', 'is_system')) {
            $payload['is_system'] = false;
        }
        if (Schema::hasColumn('roles', 'is_active')) {
            $payload['is_active'] = true;
        }

        $newRole = Role::create($payload);

        AuditLog::record(
            'role.duplicated',
            $newRole,
            ['source_role_id' => $role->id, 'source_slug' => $role->slug],
            ['name' => $newRole->name, 'slug' => $newRole->slug, 'permissions' => $clonedPerms],
            "Duplicated role {$role->name} to {$newRole->name}",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.roles.edit', $newRole)
            ->with('success', __('Role duplicated as :name.', ['name' => $newRole->name]));
    }

    public function toggleActive(Request $request, Role $role): RedirectResponse
    {
        $actor = $this->authorizeAdmin('roles.edit');
        $companyId = $this->requireCompanyId();
        $this->ensureRoleInCompany($role, $companyId);

        if ($role->isSystemRole()) {
            return back()->with('error', __('Predefined system roles cannot be deactivated.'));
        }

        $currentActive = (bool) ($role->is_active ?? true);
        $newActive = ! $currentActive;

        if (! $newActive && $role->assignedUsersCount() > 0) {
            return back()->with('error', __('Cannot deactivate a custom role that is currently assigned to users.'));
        }

        if (Schema::hasColumn('roles', 'is_active')) {
            $role->update(['is_active' => $newActive]);
        }

        AuditLog::record(
            $newActive ? 'role.activated' : 'role.deactivated',
            $role,
            ['is_active' => $currentActive],
            ['is_active' => $newActive],
            ($newActive ? 'Activated' : 'Deactivated') . " custom role {$role->name}",
            $actor->id,
            $companyId
        );

        return back()->with('success', $newActive
            ? __('Role :name activated.', ['name' => $role->name])
            : __('Role :name deactivated.', ['name' => $role->name]));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $actor = $this->authorizeAdmin('roles.delete');
        $companyId = $this->requireCompanyId();
        $this->ensureRoleInCompany($role, $companyId);

        if ($role->isSystemRole()) {
            return back()->with('error', __('System roles are protected and cannot be deleted.'));
        }

        $assignedCount = $role->assignedUsersCount();
        $reassignRoleId = $request->filled('reassign_role_id') ? (int) $request->input('reassign_role_id') : null;

        if ($assignedCount > 0 && ! $reassignRoleId) {
            return back()->with('error', __('Cannot delete role :name because :count user(s) are assigned to it. Select a replacement role first.', [
                'name' => $role->name,
                'count' => $assignedCount,
            ]));
        }

        DB::transaction(function () use ($role, $companyId, $assignedCount, $reassignRoleId) {
            if ($assignedCount > 0 && $reassignRoleId) {
                $targetRole = Role::where('company_id', $companyId)
                    ->where('id', '!=', $role->id)
                    ->findOrFail($reassignRoleId);

                DB::table('company_user')
                    ->where('company_id', $companyId)
                    ->where('role_id', $role->id)
                    ->update([
                        'role_id' => $targetRole->id,
                        'updated_at' => now(),
                    ]);
            }

            $role->delete();
        });

        AuditLog::record(
            'role.deleted',
            null,
            ['role_id' => $role->id, 'name' => $role->name, 'slug' => $role->slug],
            ['reassigned_to_role_id' => $reassignRoleId, 'reassigned_users_count' => $assignedCount],
            "Deleted custom role {$role->name}",
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('Custom role :name has been deleted.', ['name' => $role->name]));
    }

    public function matrix(): View
    {
        $this->authorizeAdmin('roles.view');
        $companyId = $this->requireCompanyId();

        $roles = Role::where('company_id', $companyId)->orderByDesc('is_system')->orderBy('id')->get();
        $modules = PermissionCatalog::modules();

        return view('admin.roles.matrix', compact('roles', 'modules'));
    }

    public function updateMatrix(Request $request): RedirectResponse
    {
        $actor = $this->authorizeAdmin('roles.edit');
        $companyId = $this->requireCompanyId();

        $submittedMatrix = (array) $request->input('matrix', []);
        $allowedKeys = PermissionCatalog::assignableCustomRolePermissionKeys();

        $roles = Role::where('company_id', $companyId)->get();
        $updatedRoles = [];

        DB::transaction(function () use ($roles, $submittedMatrix, $allowedKeys, &$updatedRoles) {
            foreach ($roles as $role) {
                if ($role->isProtectedAdminRole()) {
                    continue;
                }

                $rolePermsInput = (array) ($submittedMatrix[$role->id] ?? []);
                $cleanPerms = array_values(array_unique(array_intersect($rolePermsInput, $allowedKeys)));

                $role->update([
                    'permissions' => array_merge(['__customized__'], $cleanPerms),
                ]);

                $updatedRoles[$role->slug] = count($cleanPerms);
            }
        });

        AuditLog::record(
            'role.matrix_updated',
            null,
            null,
            ['updated_role_permission_counts' => $updatedRoles],
            'Updated company-wide role permission matrix',
            $actor->id,
            $companyId
        );

        return redirect()
            ->route('admin.roles.matrix')
            ->with('success', __('Role permission matrix updated successfully.'));
    }
}
