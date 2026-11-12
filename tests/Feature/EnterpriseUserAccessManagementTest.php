<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\Security\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnterpriseUserAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected User $companyAdmin;
    protected Role $adminRole;
    protected Role $siteManagerRole;
    protected Role $supervisorRole;
    protected Role $officeStaffRole;
    protected Site $siteA;
    protected Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction',
            'code' => 'NSD-ENT',
            'is_active' => true,
        ]);

        $this->adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->siteManagerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => PermissionCatalog::defaultRolePermissions()['site_manager'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => PermissionCatalog::defaultRolePermissions()['site_supervisor'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->officeStaffRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Office Staff',
            'slug' => 'office_staff',
            'permissions' => PermissionCatalog::defaultRolePermissions()['office_staff'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->companyAdmin = User::create([
            'name' => 'NSD Company Admin',
            'email' => 'admin@nsd.local',
            'password' => Hash::make('AdminSecret#2026'),
            'is_active' => true,
        ]);

        $this->companyAdmin->companies()->attach($this->company->id, [
            'role_id' => $this->adminRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-01',
            'name' => 'Expressway Package 1',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-A',
            'name' => 'Thane Creek Yard A',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-B',
            'name' => 'Navi Mumbai Depot B',
            'status' => 'active',
        ]);
    }

    public function test_company_admin_can_create_new_employee_user_account_and_password_is_hashed(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Vikram Deshmukh',
                'email' => 'Vikram.Deshmukh@nsd.local',
                'phone' => '+91 9820011223',
                'role_id' => $this->siteManagerRole->id,
                'password_mode' => 'manual',
                'password' => 'StrongPass#2026',
                'password_confirmation' => 'StrongPass#2026',
                'must_change_password' => '1',
                'is_active' => '1',
                'restrict_to_assigned_sites' => '1',
                'site_ids' => [$this->siteA->id],
            ]);

        $user = User::where('email', 'vikram.deshmukh@nsd.local')->first();
        $this->assertNotNull($user);
        $response->assertRedirect(route('admin.users.show', $user));

        // Verify password is stored hashed and never plain text
        $rawPasswordInDb = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertNotSame('StrongPass#2026', $rawPasswordInDb);
        $this->assertTrue(Hash::check('StrongPass#2026', $rawPasswordInDb));
        $this->assertTrue((bool) $user->must_change_password);

        // Verify role and site assignment
        $this->assertSame('site_manager', $user->roleInCompany($this->company->id)?->slug);
        $this->assertTrue($user->isAssignedToSite($this->siteA->id));
        $this->assertFalse($user->isAssignedToSite($this->siteB->id));

        // Verify audit log recorded without plain password
        $audit = AuditLog::where('action', 'user.created')->where('subject_id', $user->id)->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('StrongPass#2026', json_encode($audit->new_values));
    }

    public function test_unauthorized_staff_cannot_access_user_or_role_management_or_create_users(): void
    {
        $staff = User::create([
            'name' => 'Supervisor Staff',
            'email' => 'sup@nsd.local',
            'password' => Hash::make('StaffPass#2026'),
            'is_active' => true,
        ]);
        $staff->companies()->attach($this->company->id, [
            'role_id' => $this->supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($staff)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.users.index'))
            ->assertStatus(403);

        $this->actingAs($staff)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Rogue User',
                'email' => 'rogue@nsd.local',
                'role_id' => $this->adminRole->id,
                'password_mode' => 'auto',
            ])
            ->assertStatus(403);

        $this->actingAs($staff)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.roles.index'))
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'rogue@nsd.local']);
    }

    public function test_duplicate_email_is_rejected_when_creating_user(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate Admin',
                'email' => 'admin@nsd.local',
                'role_id' => $this->siteManagerRole->id,
                'password_mode' => 'auto',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_company_admin_can_create_custom_role_and_privilege_escalation_is_blocked(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.roles.store'), [
                'name' => 'Senior Accountant',
                'slug' => 'senior_accountant',
                'description' => 'Handles RA billing, tax invoices, and payroll verification.',
                'permissions' => [
                    'dashboard.view',
                    'work_orders.view',
                    'ra_bills.view',
                    'ra_bills.create',
                    'ra_bills.export',
                    'invoices.view',
                    'invoices.export',
                    'payroll.view',
                    '*', // Privilege escalation attempt
                    'users.create', // Privilege escalation attempt
                    'roles.update', // Privilege escalation attempt
                ],
            ]);

        $customRole = Role::where('company_id', $this->company->id)->where('slug', 'senior_accountant')->first();
        $this->assertNotNull($customRole);
        $response->assertRedirect(route('admin.roles.edit', $customRole));
        $this->assertFalse($customRole->isSystemRole());
        $this->assertTrue($customRole->hasPermission('ra_bills.create'));
        $this->assertTrue($customRole->hasPermission('invoices.export'));
        $this->assertFalse($customRole->hasPermission('*'));
        $this->assertFalse($customRole->hasPermission('users.create'));
        $this->assertFalse($customRole->hasPermission('roles.update'));

        // Assign custom role to an employee and verify enforcement
        $accountant = User::create([
            'name' => 'Priya Kulkarni',
            'email' => 'priya.accountant@nsd.local',
            'password' => Hash::make('Accountant#2026'),
            'is_active' => true,
        ]);
        $accountant->companies()->attach($this->company->id, [
            'role_id' => $customRole->id,
            'status' => 'active',
            'is_primary' => true,
            'restrict_to_assigned_sites' => false,
        ]);

        $this->assertTrue($accountant->hasPermission('ra_bills.create'));
        $this->assertTrue($accountant->hasPermission('payroll.view'));
        $this->assertFalse($accountant->hasPermission('workforce.create'));
        $this->assertFalse($accountant->hasPermission('users.create'));

        // Accountant can view payroll index (granted by custom role)
        $this->actingAs($accountant)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.index'))
            ->assertStatus(200);

        // Accountant cannot create a worker (not granted in custom role)
        $this->actingAs($accountant)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('workforce.create'))
            ->assertStatus(403);
    }

    public function test_individual_user_allow_and_deny_permission_overrides_and_reset_to_role_defaults(): void
    {
        $officeUser = User::create([
            'name' => 'Meera Joshi',
            'email' => 'meera@nsd.local',
            'password' => Hash::make('MeeraPass#2026'),
            'is_active' => true,
        ]);
        $officeUser->companies()->attach($this->company->id, [
            'role_id' => $this->officeStaffRole->id,
            'status' => 'active',
            'is_primary' => true,
            'restrict_to_assigned_sites' => false,
        ]);
        $officeUser->sites()->attach($this->siteA->id, ['role_in_site' => 'office_staff']);

        $wo = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'order_number' => 'WO-PERM-001',
            'title' => 'Foundation Excavation',
            'order_type' => 'subcontract',
            'status' => 'approved',
            'created_by' => $this->companyAdmin->id,
        ]);
        WorkOrderItem::create([
            'work_order_id' => $wo->id,
            'sr_no' => '1',
            'description' => 'Earthwork Excavation',
            'unit' => 'CUM',
            'ordered_quantity' => 100,
            'rate' => 500,
            'amount' => 50000,
        ]);
        $raBill = RaBill::create([
            'company_id' => $this->company->id,
            'work_order_id' => $wo->id,
            'site_id' => $this->siteA->id,
            'ra_number' => 1,
            'ra_label' => '1st RA Bill',
            'bill_number' => 'RA-001',
            'period_from' => '2026-11-01',
            'period_to' => '2026-11-10',
            'status' => 'draft',
            'created_by' => $this->companyAdmin->id,
        ]);

        // 1. Inherited permissions: Office Staff has ra_bills.export by default, does NOT have procurement.request
        $this->assertTrue($officeUser->hasPermission('ra_bills.export'));
        $this->assertFalse($officeUser->hasPermission('procurement.request'));

        // 2. Apply individual user overrides: ALLOW procurement.request, DENY ra_bills.export & invoices.export
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('admin.users.permissions.update', $officeUser), [
                'overrides' => [
                    'procurement.request' => 'allow',
                    'ra_bills.export' => 'deny',
                    'invoices.export' => 'deny',
                    'work_orders.view' => 'inherit',
                ],
            ])
            ->assertRedirect(route('admin.users.show', $officeUser));

        $officeUser->refresh();
        $this->assertTrue($officeUser->hasPermission('procurement.request'));
        $this->assertFalse($officeUser->hasPermission('ra_bills.export'));
        $this->assertTrue($officeUser->isExplicitlyDenied('ra_bills.export'));

        // Office user can now access Purchase Request creation (due to explicit ALLOW override)
        $this->actingAs($officeUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('procurement.create_request'))
            ->assertStatus(200);

        // Office user is blocked from exporting RA Bill PDF/XLSX (due to explicit DENY override)
        $this->actingAs($officeUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.ra_bills.export_xlsx', [$wo, $raBill]))
            ->assertStatus(403);

        $this->actingAs($officeUser)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.ra_bills.export_pdf', [$wo, $raBill, 'tax_invoice']))
            ->assertStatus(403);

        // 3. Reset overrides back to role defaults
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.users.permissions.reset', $officeUser))
            ->assertRedirect(route('admin.users.show', $officeUser));

        $officeUser->refresh();
        $this->assertSame([], $officeUser->permissionOverrides($this->company->id));
        $this->assertTrue($officeUser->hasPermission('ra_bills.export'));
        $this->assertFalse($officeUser->hasPermission('procurement.request'));
    }

    public function test_site_restricted_user_can_access_assigned_site_a_but_is_blocked_from_unassigned_site_b(): void
    {
        $siteManagerA = User::create([
            'name' => 'Site Manager A',
            'email' => 'manager.a@nsd.local',
            'password' => Hash::make('ManagerAPass#2026'),
            'is_active' => true,
        ]);
        $siteManagerA->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
            'restrict_to_assigned_sites' => true,
        ]);

        // Assign only Site A via UserManagementController@updateSites
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('admin.users.sites.update', $siteManagerA), [
                'restrict_to_assigned_sites' => '1',
                'site_ids' => [$this->siteA->id],
            ])
            ->assertRedirect(route('admin.users.show', $siteManagerA));

        $woSiteA = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'order_number' => 'WO-SITE-A',
            'title' => 'Site A Piling',
            'order_type' => 'subcontract',
            'status' => 'approved',
            'created_by' => $this->companyAdmin->id,
        ]);

        $woSiteB = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteB->id,
            'order_number' => 'WO-SITE-B',
            'title' => 'Site B Grading',
            'order_type' => 'subcontract',
            'status' => 'approved',
            'created_by' => $this->companyAdmin->id,
        ]);

        // Can view Site A and Work Order on Site A
        $this->actingAs($siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('sites.show', $this->siteA))
            ->assertStatus(200);

        $this->actingAs($siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.show', $woSiteA))
            ->assertStatus(200);

        // Direct URL to Site B or Work Order on Site B returns 403
        $this->actingAs($siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('sites.show', $this->siteB))
            ->assertStatus(403);

        $this->actingAs($siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.show', $woSiteB))
            ->assertStatus(403);
    }

    public function test_cross_company_isolation_blocks_admin_from_managing_other_company_users_or_roles(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Infra Pvt Ltd',
            'code' => 'OTH-01',
            'is_active' => true,
        ]);
        $otherRole = Role::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Custom Role',
            'slug' => 'other_custom',
            'permissions' => ['dashboard.view'],
            'is_system' => false,
        ]);
        $otherUser = User::create([
            'name' => 'Other Company Employee',
            'email' => 'other@infra.local',
            'password' => Hash::make('OtherPass#2026'),
            'is_active' => true,
        ]);
        $otherUser->companies()->attach($otherCompany->id, [
            'role_id' => $otherRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // NSD Company Admin cannot view or edit Other Company's user or role
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.users.show', $otherUser))
            ->assertStatus(403);

        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.roles.edit', $otherRole))
            ->assertStatus(404);
    }

    public function test_deactivated_user_cannot_login_or_continue_active_session_and_last_admin_is_protected(): void
    {
        // Last active Company Admin cannot deactivate themselves
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('users.toggle_active', $this->companyAdmin))
            ->assertSessionHas('error');

        $this->assertTrue($this->companyAdmin->fresh()->isActiveInCompany($this->company->id));

        // Create and then deactivate an employee account
        $employee = User::create([
            'name' => 'Active Employee',
            'email' => 'employee@nsd.local',
            'password' => Hash::make('EmpPass#2026'),
            'is_active' => true,
        ]);
        $employee->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('users.toggle_active', $employee))
            ->assertSessionHas('success');

        $this->assertFalse($employee->fresh()->isActiveInCompany($this->company->id));

        // Deactivated employee cannot access dashboard even with an active session
        $this->actingAs($employee->fresh())
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_password_reset_forces_password_change_on_next_login_and_records_audit_log(): void
    {
        $employee = User::create([
            'name' => 'Reset Target Employee',
            'email' => 'reset.target@nsd.local',
            'password' => Hash::make('OldPass#2026'),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $employee->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.users.password.reset', $employee), [
                'password_mode' => 'manual',
                'password' => 'TempReset#2026',
                'password_confirmation' => 'TempReset#2026',
                'must_change_password' => '1',
            ])
            ->assertRedirect(route('admin.users.show', $employee));

        $employee->refresh();
        $this->assertTrue(Hash::check('TempReset#2026', $employee->password));
        $this->assertTrue((bool) $employee->must_change_password);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.password_reset_by_admin',
            'subject_id' => $employee->id,
        ]);

        // When employee visits dashboard, SetTenantContext redirects to account.password.show
        $this->actingAs($employee)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('account.password.show'));

        // Employee updates their own password
        $this->actingAs($employee)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('account.password.update'), [
                'current_password' => 'TempReset#2026',
                'password' => 'MyNewSecure#2026',
                'password_confirmation' => 'MyNewSecure#2026',
            ])
            ->assertRedirect(route('dashboard'));

        $employee->refresh();
        $this->assertFalse((bool) $employee->must_change_password);
        $this->assertTrue(Hash::check('MyNewSecure#2026', $employee->password));
    }

    public function test_role_matrix_update_and_custom_role_duplication_and_deletion_with_reassignment(): void
    {
        // Create a custom role
        $customRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Procurement Officer',
            'slug' => 'procurement_officer',
            'permissions' => PermissionCatalog::customRoleTemplates()['procurement_officer']['permissions'],
            'is_system' => false,
            'is_active' => true,
        ]);

        // Duplicate the role
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('admin.roles.duplicate', $customRole))
            ->assertRedirect();

        $duplicated = Role::where('company_id', $this->company->id)
            ->where('slug', 'like', 'procurement_officer_copy%')
            ->first();
        $this->assertNotNull($duplicated);
        $this->assertFalse($duplicated->isSystemRole());

        // Update the permission matrix
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('admin.roles.matrix.update'), [
                'matrix' => [
                    $customRole->id => ['dashboard.view', 'procurement.view', 'procurement.request', 'inventory.view'],
                ],
            ])
            ->assertRedirect(route('admin.roles.matrix'));

        $customRole->refresh();
        $this->assertTrue($customRole->hasPermission('procurement.request'));
        $this->assertFalse($customRole->hasPermission('procurement.approve'));

        // Assign a user to $duplicated role, then delete $duplicated with reassignment to $customRole
        $procUser = User::create([
            'name' => 'Rohan More',
            'email' => 'rohan.more@nsd.local',
            'password' => Hash::make('RohanPass#2026'),
            'is_active' => true,
        ]);
        $procUser->companies()->attach($this->company->id, [
            'role_id' => $duplicated->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Deleting a system role must fail
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->delete(route('admin.roles.destroy', $this->siteManagerRole))
            ->assertSessionHas('error');
        $this->assertNotNull(Role::find($this->siteManagerRole->id));

        // Deleting custom role with reassignment succeeds
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->delete(route('admin.roles.destroy', $duplicated), [
                'reassign_role_id' => $customRole->id,
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertNull(Role::find($duplicated->id));
        $this->assertSame($customRole->id, $procUser->fresh()->roleInCompany($this->company->id)?->id);
    }

    public function test_manager_2fa_remains_functional_and_no_sensitive_secrets_leak_in_user_management_views(): void
    {
        $manager = User::create([
            'name' => '2FA Site Manager',
            'email' => '2fa.manager@nsd.local',
            'password' => Hash::make('SecretPass#9988'),
            'is_active' => true,
            'two_factor_enabled' => true,
            'two_factor_channel' => 'totp',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $manager->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->assertTrue($manager->requiresTwoFactor());

        // Inspect user list and user show pages — neither password hash nor raw TOTP secret may appear in HTML
        $indexHtml = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.users.index'))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('SecretPass#9988', $indexHtml);
        $this->assertStringNotContainsString($manager->password, $indexHtml);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $indexHtml);

        $showHtml = $this->actingAs($this->companyAdmin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.users.show', $manager))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('SecretPass#9988', $showHtml);
        $this->assertStringNotContainsString($manager->password, $showHtml);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $showHtml);
    }
}
