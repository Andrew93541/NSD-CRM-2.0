<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendancePunch;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UxRoleDashboardAndAttendanceRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected Site $siteA;
    protected Site $siteB;
    protected Worker $workerA;
    protected User $companyAdmin;
    protected User $seniorManager;
    protected User $siteManager;
    protected User $supervisor;
    protected User $officeStaff;
    protected User $warehouseManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Infrastructure Pvt Ltd',
            'code' => 'NSD-UX',
            'is_active' => true,
        ]);
        TenantContext::setCompanyId($this->company->id);

        $defaults = PermissionCatalog::defaultRolePermissions();

        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'], // Legacy wildcard to verify it cannot bypass attendance restrictions
        ]);

        $seniorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => $defaults['senior_manager'],
        ]);

        $siteManagerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => $defaults['site_manager'],
        ]);

        $supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => $defaults['site_supervisor'],
        ]);

        $officeRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Office Staff',
            'slug' => 'office_staff',
            'permissions' => $defaults['office_staff'],
        ]);

        $warehouseRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Warehouse Manager',
            'slug' => 'warehouse_manager',
            'permissions' => $defaults['warehouse_manager'],
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Metro Corridor Line 4',
            'code' => 'MC-L4',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Pier 12 Construction Site',
            'code' => 'PIER-12',
            'location' => 'Thane West',
            'status' => 'active',
            'kiosk_token' => 'preserved-kiosk-token-site-a-123456',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Depot Casting Yard',
            'code' => 'DCY-02',
            'location' => 'Kasheli',
            'status' => 'active',
        ]);

        $this->workerA = Worker::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'code' => 'W-UX-101',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'worker_type' => 'direct_labour',
            'trade_designation' => 'Mason',
            'daily_rate' => 850,
            'status' => 'active',
        ]);

        $this->companyAdmin = $this->createEmployee('Admin User', 'admin@nsd-ux.test', $adminRole);
        $this->seniorManager = $this->createEmployee('Senior VP', 'senior@nsd-ux.test', $seniorRole);
        $this->siteManager = $this->createEmployee('Site Manager', 'sitemgr@nsd-ux.test', $siteManagerRole, [$this->siteA->id]);
        $this->supervisor = $this->createEmployee('Site Supervisor', 'supervisor@nsd-ux.test', $supervisorRole, [$this->siteA->id]);
        $this->officeStaff = $this->createEmployee('Office Executive', 'office@nsd-ux.test', $officeRole);
        $this->warehouseManager = $this->createEmployee('Store Keeper', 'store@nsd-ux.test', $warehouseRole, [$this->siteA->id]);
    }

    protected function createEmployee(string $name, string $email, Role $role, array $siteIds = []): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Password#123'),
            'is_active' => true,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        if (!empty($siteIds)) {
            $user->sites()->sync($siteIds);
        }

        return $user;
    }

    public function test_default_attendance_permission_matrix_across_all_roles(): void
    {
        // 1. Company Admin: view=allow, export=allow, mark=deny, edit=deny, approve=deny
        $this->assertTrue($this->companyAdmin->hasPermission('attendance.view'));
        $this->assertTrue($this->companyAdmin->hasPermission('attendance.export'));
        $this->assertFalse($this->companyAdmin->hasPermission('attendance.mark'));
        $this->assertFalse($this->companyAdmin->hasPermission('attendance.record'));
        $this->assertFalse($this->companyAdmin->hasPermission('attendance.muster'));
        $this->assertFalse($this->companyAdmin->hasPermission('attendance.edit'));
        $this->assertFalse($this->companyAdmin->hasPermission('attendance.approve'));

        // 2. Senior Manager: view=allow, export=allow, mark=deny, edit=deny, approve=deny
        $this->assertTrue($this->seniorManager->hasPermission('attendance.view'));
        $this->assertTrue($this->seniorManager->hasPermission('attendance.export'));
        $this->assertFalse($this->seniorManager->hasPermission('attendance.mark'));
        $this->assertFalse($this->seniorManager->hasPermission('attendance.edit'));
        $this->assertFalse($this->seniorManager->hasPermission('attendance.approve'));

        // 3. Site Manager: view=allow, mark=allow, edit=allow, export=allow, approve=deny
        $this->assertTrue($this->siteManager->hasPermission('attendance.view'));
        $this->assertTrue($this->siteManager->hasPermission('attendance.mark'));
        $this->assertTrue($this->siteManager->hasPermission('attendance.record'));
        $this->assertTrue($this->siteManager->hasPermission('attendance.muster'));
        $this->assertTrue($this->siteManager->hasPermission('attendance.edit'));
        $this->assertTrue($this->siteManager->hasPermission('attendance.export'));
        $this->assertFalse($this->siteManager->hasPermission('attendance.approve'));

        // 4. Site Supervisor: view=allow, mark=allow, edit=allow, approve=deny, export=deny
        $this->assertTrue($this->supervisor->hasPermission('attendance.view'));
        $this->assertTrue($this->supervisor->hasPermission('attendance.mark'));
        $this->assertTrue($this->supervisor->hasPermission('attendance.edit'));
        $this->assertFalse($this->supervisor->hasPermission('attendance.approve'));
        $this->assertFalse($this->supervisor->hasPermission('attendance.export'));

        // 5. Office Staff: view=allow, export=deny by default, mark=deny, edit=deny, approve=deny
        $this->assertTrue($this->officeStaff->hasPermission('attendance.view'));
        $this->assertFalse($this->officeStaff->hasPermission('attendance.export'));
        $this->assertFalse($this->officeStaff->hasPermission('attendance.mark'));
        $this->assertFalse($this->officeStaff->hasPermission('attendance.edit'));
        $this->assertFalse($this->officeStaff->hasPermission('attendance.approve'));
    }

    public function test_company_admin_cannot_bypass_attendance_restrictions_via_direct_urls_or_posts(): void
    {
        $attendance = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $this->workerA->id,
            'attendance_date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'worked_hours' => 9.0,
            'status' => 'present',
            'recorded_by' => $this->supervisor->id,
        ]);

        $correction = AttendanceCorrection::create([
            'company_id' => $this->company->id,
            'daily_attendance_id' => $attendance->id,
            'requested_by' => $this->supervisor->id,
            'previous_clock_in' => '09:00:00',
            'previous_clock_out' => '18:00:00',
            'previous_worked_hours' => 9.0,
            'previous_status' => 'present',
            'proposed_clock_in' => '08:30:00',
            'proposed_clock_out' => '18:00:00',
            'proposed_worked_hours' => 9.5,
            'proposed_status' => 'present',
            'reason' => 'Worker arrived 30 minutes early for concrete pouring.',
            'status' => 'pending',
        ]);

        // 1. Company Admin CAN view Monthly Attendance Register (default mode=month) and Export CSV
        $registerResponse = $this->actingAs($this->companyAdmin)->get(route('attendance.register'));
        $registerResponse->assertStatus(200);
        $registerResponse->assertSee('Month-Wise Attendance Register Matrix');
        $registerResponse->assertSee('Read-Only Attendance View');
        $registerResponse->assertDontSee('Mark Daily Muster');

        $exportResponse = $this->actingAs($this->companyAdmin)->get(route('attendance.export_payroll'));
        $exportResponse->assertStatus(200);

        // 2. Company Admin CANNOT open Daily Muster or submit attendance or QR scans or corrections or approvals
        $this->actingAs($this->companyAdmin)->get(route('attendance.muster'))->assertStatus(403);

        $this->actingAs($this->companyAdmin)->post(route('attendance.store_muster'), [
            'site_id' => $this->siteA->id,
            'attendance_date' => '2026-11-10',
            'workers' => [
                $this->workerA->id => [
                    ['status' => 'present', 'clock_in' => '09:00', 'clock_out' => '17:00'],
                ],
            ],
        ])->assertStatus(403);

        $this->actingAs($this->companyAdmin)->post(route('attendance.qr_scan'), [
            'site_id' => $this->siteA->id,
            'worker_code' => $this->workerA->code,
            'scan_type' => 'clock_in',
        ])->assertStatus(403);

        $this->actingAs($this->companyAdmin)->post(route('attendance.request_correction', $attendance), [
            'proposed_clock_in' => '08:00',
            'proposed_clock_out' => '17:00',
            'proposed_status' => 'present',
            'reason' => 'Admin attempting silent correction',
        ])->assertStatus(403);

        $this->actingAs($this->companyAdmin)->post(route('attendance.review_correction', $correction), [
            'action' => 'approved',
        ])->assertStatus(403);
    }

    public function test_audited_attendance_correction_workflow_requires_explicit_approval_permission(): void
    {
        $attendance = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $this->workerA->id,
            'attendance_date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
            'worked_hours' => 8.0,
            'status' => 'present',
            'recorded_by' => $this->supervisor->id,
        ]);

        // 1. Supervisor submits correction request with mandatory reason
        $requestResponse = $this->actingAs($this->supervisor)->post(route('attendance.request_correction', $attendance), [
            'proposed_clock_in' => '08:00',
            'proposed_clock_out' => '18:00',
            'proposed_status' => 'present',
            'reason' => 'Verified gate register entry at 08:00 AM.',
        ]);
        $requestResponse->assertRedirect();

        // Original attendance values must remain unchanged while pending
        $attendance->refresh();
        $this->assertSame('09:00:00', $attendance->clock_in);
        $this->assertSame('17:00:00', $attendance->clock_out);

        $correction = AttendanceCorrection::where('daily_attendance_id', $attendance->id)->firstOrFail();
        $this->assertSame('pending', $correction->status);
        $this->assertSame('09:00:00', $correction->previous_clock_in);
        $this->assertSame('17:00:00', $correction->previous_clock_out);

        // AuditLog recorded for correction request
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $this->company->id,
            'action' => 'attendance.correction_requested',
            'user_id' => $this->supervisor->id,
        ]);

        // 2. Site Manager & Senior Manager CANNOT approve by default (attendance.approve = deny)
        $this->actingAs($this->siteManager)->post(route('attendance.review_correction', $correction), [
            'action' => 'approved',
        ])->assertStatus(403);

        $this->actingAs($this->seniorManager)->post(route('attendance.review_correction', $correction), [
            'action' => 'approved',
        ])->assertStatus(403);

        // 3. Company Admin explicitly grants attendance.approve to Site Manager
        $this->actingAs($this->companyAdmin)->put(route('admin.users.permissions.update', $this->siteManager), [
            'overrides' => [
                'attendance.approve' => 'allow',
            ],
        ])->assertRedirect();

        $this->siteManager->refresh();
        $this->assertTrue($this->siteManager->hasPermission('attendance.approve'));

        // 4. Non-self-approval enforced: if supervisor is also granted attendance.approve, supervisor still cannot approve their own request
        $this->supervisor->companies()->updateExistingPivot($this->company->id, [
            'permission_overrides' => json_encode(['attendance.approve' => 'allow']),
        ]);
        $this->supervisor->refresh();
        $this->actingAs($this->supervisor)->post(route('attendance.review_correction', $correction), [
            'action' => 'approved',
        ])->assertStatus(403);

        // 5. Explicitly authorized Site Manager approves the correction
        $approveResponse = $this->actingAs($this->siteManager)->post(route('attendance.review_correction', $correction), [
            'action' => 'approved',
        ]);
        $approveResponse->assertRedirect();

        $correction->refresh();
        $attendance->refresh();
        $this->assertSame('approved', $correction->status);
        $this->assertSame('09:00:00', $correction->previous_clock_in);
        $this->assertSame('08:00:00', $attendance->clock_in);
        $this->assertSame('18:00:00', $attendance->clock_out);

        // AuditLog recorded for correction review
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $this->company->id,
            'action' => 'attendance.correction_reviewed',
            'user_id' => $this->siteManager->id,
        ]);
    }

    public function test_explicit_user_permission_overrides_and_legacy_alias_precedence(): void
    {
        // 1. Office Staff cannot export attendance CSV by default
        $this->actingAs($this->officeStaff)->get(route('attendance.export_payroll'))->assertStatus(403);

        // Grant attendance.export explicitly to Office Staff
        $this->actingAs($this->companyAdmin)->put(route('admin.users.permissions.update', $this->officeStaff), [
            'overrides' => [
                'attendance.export' => 'allow',
            ],
        ])->assertRedirect();

        $this->officeStaff->refresh();
        $this->assertTrue($this->officeStaff->hasPermission('attendance.export'));
        $this->actingAs($this->officeStaff)->get(route('attendance.export_payroll'))->assertStatus(200);

        // 2. Explicit user deny on Company Admin blocks attendance.export even though Company Admin role has '*'
        $this->actingAs($this->companyAdmin)->put(route('admin.users.permissions.update', $this->companyAdmin), [
            'overrides' => [
                'attendance.export' => 'deny',
            ],
        ])->assertRedirect();

        $this->companyAdmin->refresh();
        $this->assertFalse($this->companyAdmin->hasPermission('attendance.export'));
        $this->actingAs($this->companyAdmin)->get(route('attendance.export_payroll'))->assertStatus(403);

        // 3. Legacy attendance.record / attendance.muster compatibility and explicit deny precedence
        $customLegacyRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Legacy Field Clerk',
            'slug' => 'legacy_field_clerk',
            'permissions' => ['attendance.record'],
        ]);
        $legacyUser = $this->createEmployee('Legacy Clerk', 'legacy@nsd-ux.test', $customLegacyRole, [$this->siteA->id]);

        // Having legacy attendance.record grants attendance.mark and attendance.muster
        $this->assertTrue($legacyUser->hasPermission('attendance.mark'));
        $this->assertTrue($legacyUser->hasPermission('attendance.muster'));
        $this->actingAs($legacyUser)->get(route('attendance.muster'))->assertStatus(200);

        // Explicit user deny on attendance.mark overrides legacy attendance.record
        $legacyUser->companies()->updateExistingPivot($this->company->id, [
            'permission_overrides' => json_encode(['attendance.mark' => 'deny']),
        ]);
        $legacyUser->refresh();
        $this->assertFalse($legacyUser->hasPermission('attendance.mark'));
        $this->assertFalse($legacyUser->hasPermission('attendance.record'));
        $this->assertFalse($legacyUser->hasPermission('attendance.muster'));
        $this->actingAs($legacyUser)->get(route('attendance.muster'))->assertStatus(403);
    }

    public function test_site_kiosk_removed_from_navigation_and_disabled_by_default_while_preserving_data(): void
    {
        // Create a historical kiosk punch record
        $historicalPunch = AttendancePunch::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $this->workerA->id,
            'punched_at' => '2026-11-01 08:55:00',
            'punch_type' => 'in',
            'source' => 'kiosk',
            'device_id' => 'KIOSK-GATE-01',
        ]);

        // 1. Sidebar does not contain Site Kiosk
        $dashboard = $this->actingAs($this->companyAdmin)->get(route('dashboard'));
        $dashboard->assertStatus(200);
        $dashboard->assertDontSee('Site Kiosk');
        $dashboard->assertDontSee('/attendance/kiosk');

        // 2. Direct Kiosk URLs return 404 by default
        $this->actingAs($this->companyAdmin)->get(route('attendance.kiosk'))->assertStatus(404);
        $this->actingAs($this->companyAdmin)->post(route('attendance.kiosk.rotate', $this->siteA))->assertStatus(404);
        $this->get(route('kiosk.terminal', $this->siteA->kiosk_token))->assertStatus(404);
        $this->post(route('kiosk.punch', $this->siteA->kiosk_token), [
            'mode' => 'qr',
            'qr_token' => 'any',
            'punch_type' => 'in',
        ])->assertStatus(404);

        // 3. Historical kiosk punch and site kiosk_token column remain preserved
        $this->assertDatabaseHas('attendance_punches', [
            'id' => $historicalPunch->id,
            'source' => 'kiosk',
            'device_id' => 'KIOSK-GATE-01',
        ]);
        $this->assertDatabaseHas('sites', [
            'id' => $this->siteA->id,
            'kiosk_token' => 'preserved-kiosk-token-site-a-123456',
        ]);
    }

    public function test_role_based_dashboards_and_verified_sidebar_navigation_routes(): void
    {
        // 1. Company Admin Dashboard focuses on the 5 approved areas and hides Muster Roll button
        $adminDashboard = $this->actingAs($this->companyAdmin)->get(route('dashboard'));
        $adminDashboard->assertStatus(200);
        $adminDashboard->assertSee('Company Setup Progress');
        $adminDashboard->assertSee('Employee & Access Management');
        $adminDashboard->assertSee('Active Projects');
        $adminDashboard->assertSee('Active On Sites Today');
        $adminDashboard->assertSee('Compliance & Field Alerts');
        $adminDashboard->assertDontSee('Take Muster Roll');

        // 2. Office Staff Dashboard shows Finance & Payroll focus
        $officeDashboard = $this->actingAs($this->officeStaff)->get(route('dashboard'));
        $officeDashboard->assertStatus(200);
        $officeDashboard->assertSee('Office & Finance Operations');
        $officeDashboard->assertDontSee('Take Muster Roll');

        // 3. Verify every sidebar navigation route resolves with 200 OK for authorized users
        $verifiedAdminRoutes = [
            route('dashboard'),
            route('projects.index'),
            route('sites.index'),
            route('work_orders.index'),
            route('tasks.index'),
            route('workforce.index'),
            route('attendance.register'),
            route('payroll.index'),
            route('procurement.requests'),
            route('inventory.stocks'),
            route('admin.users.index'),
            route('reports.multi_site'),
            route('reports.audit_logs'),
        ];

        foreach ($verifiedAdminRoutes as $url) {
            $this->actingAs($this->companyAdmin)->get($url)->assertStatus(200);
        }

        // Supervisor can access daily muster roll directly
        $this->actingAs($this->supervisor)->get(route('attendance.muster'))->assertStatus(200);
    }

    public function test_second_ux_audit_simplified_operational_modules_and_empty_states(): void
    {
        // 1. Fresh company onboarding / genuine empty states when no projects, sites, workers, or work orders exist
        $freshCompany = Company::create([
            'name' => 'Fresh Start Infrastructure Pvt Ltd',
            'code' => 'FSI-01',
            'is_active' => true,
        ]);
        $freshAdminRole = Role::create([
            'company_id' => $freshCompany->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => PermissionCatalog::defaultRolePermissions()['company_admin'],
        ]);
        $freshAdmin = User::create([
            'name' => 'Fresh Admin',
            'email' => 'admin@freshstart.test',
            'password' => bcrypt('Password123!'),
            'company_id' => $freshCompany->id,
            'role_id' => $freshAdminRole->id,
            'is_active' => true,
        ]);
        $freshAdmin->companies()->attach($freshCompany->id, [
            'role_id' => $freshAdminRole->id,
            'is_primary' => true,
        ]);

        // Genuine empty states with direct first-time setup CTAs
        $this->actingAs($freshAdmin)
            ->withSession(['active_company_id' => $freshCompany->id])
            ->get(route('workforce.index'))
            ->assertOk()
            ->assertSee('Add Your First Worker');

        $this->actingAs($freshAdmin)
            ->withSession(['active_company_id' => $freshCompany->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Create Your First Project');

        $this->actingAs($freshAdmin)
            ->withSession(['active_company_id' => $freshCompany->id])
            ->get(route('sites.index'))
            ->assertOk()
            ->assertSee('Add Your First Site');

        $this->actingAs($freshAdmin)
            ->withSession(['active_company_id' => $freshCompany->id])
            ->get(route('work_orders.index'))
            ->assertOk()
            ->assertSee('Create Your First Work Order');

        $this->actingAs($freshAdmin)
            ->withSession(['active_company_id' => $freshCompany->id])
            ->get(route('procurement.requests'))
            ->assertOk()
            ->assertSee('Raise Your First Purchase Request')
            ->assertSee('Warehouse & Yard Stock');

        // 2. Simplified Worker Registration & Profile views (plain business language, collapsible sections)
        $this->actingAs($this->companyAdmin)
            ->get(route('workforce.create'))
            ->assertOk()
            ->assertSee('Worker ID Number')
            ->assertSee('Automatically assigned when you save this profile.')
            ->assertDontSee('Assigned atomically upon save');

        $this->actingAs($this->companyAdmin)
            ->get(route('workforce.show', $this->workerA))
            ->assertOk()
            ->assertSee('Worker ID Badge & Portal PIN')
            ->assertSee('Site Assignment & Transfer History')
            ->assertDontSee('Cryptographic QR Badge Token');

        // 3. Simplified Tasks & Site Notes (4-card KPI strip, collapsible Filters, plain business labels)
        $this->actingAs($this->companyAdmin)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Site Tasks & Daily Notes')
            ->assertSee('Recent Site Activity');

        // 4. Unified Reports switcher bar across Multi-Site, Work Orders, Inventory & Audit Logs
        $this->actingAs($this->companyAdmin)
            ->get(route('reports.multi_site'))
            ->assertOk()
            ->assertSee('Site Comparison')
            ->assertSee('Work Orders Summary')
            ->assertSee('Stock Valuation');
    }
}
