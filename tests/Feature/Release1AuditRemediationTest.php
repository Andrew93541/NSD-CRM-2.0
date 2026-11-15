<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\LeaveType;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrderUpload;
use App\Services\Operations\WorkOrderUploadService;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use App\Services\Storage\VaultStorage;
use App\Services\Workforce\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class Release1AuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Site $siteA;

    private Site $siteB;

    private Project $project;

    /** @var array<string, Role> */
    private array $roles = [];

    private User $admin1;

    private User $admin2;

    private User $seniorManager;

    private User $siteManagerA;

    private User $supervisorA;

    private User $officeStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Sahyadri Infra Remediation Ltd',
            'code' => 'SIRL',
            'status' => 'active',
            'is_active' => true,
            'invoice_prefix' => 'SIRL-INV',
            'invoice_starting_number' => 1,
            'invoice_rounding_mode' => 'paisa',
            'onboarding_completed_at' => now(),
        ]);

        TenantContext::setCompanyId($this->company->id);

        $defaults = PermissionCatalog::defaultRolePermissions();
        foreach (['company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'] as $slug) {
            $this->roles[$slug] = Role::create([
                'company_id' => $this->company->id,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'permissions' => $defaults[$slug],
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-SIRL-01',
            'name' => 'Coastal Viaduct Package',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-SIRL-A',
            'name' => 'Viaduct North Pier',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-SIRL-B',
            'name' => 'Viaduct South Pier',
            'status' => 'active',
        ]);

        $this->admin1 = $this->makeUser('Admin One', 'admin1@sirl.test', $this->roles['company_admin'], false);
        $this->admin2 = $this->makeUser('Admin Two', 'admin2@sirl.test', $this->roles['company_admin'], false);
        $this->seniorManager = $this->makeUser('Senior Manager', 'senior@sirl.test', $this->roles['senior_manager'], false);
        $this->siteManagerA = $this->makeUser('Site Manager A', 'sma@sirl.test', $this->roles['site_manager'], true, [$this->siteA->id]);
        $this->supervisorA = $this->makeUser('Supervisor A', 'supa@sirl.test', $this->roles['site_supervisor'], true, [$this->siteA->id]);
        $this->officeStaff = $this->makeUser('Office Staff', 'office@sirl.test', $this->roles['office_staff'], false);
    }

    private function makeUser(
        string $name,
        string $email,
        Role $role,
        bool $restricted,
        array $siteIds = [],
        ?array $overrides = null
    ): User {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('StrongPass#2026'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => $restricted,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
            'restrict_to_assigned_sites' => $restricted,
            'permission_overrides' => $overrides ? json_encode($overrides) : null,
        ]);

        if (! empty($siteIds)) {
            $user->sites()->attach($siteIds);
        }

        return $user;
    }

    private function makeWorker(
        string $code,
        string $firstName,
        ?Site $site,
        float $dailyRate = 800.00,
        string $statutoryStatus = 'not_applicable',
        ?int $userId = null
    ): Worker {
        return Worker::create([
            'company_id' => $this->company->id,
            'user_id' => $userId,
            'code' => $code,
            'first_name' => $firstName,
            'last_name' => 'Kulkarni',
            'worker_type' => 'employee',
            'trade_designation' => 'Site Technician',
            'pay_type' => 'daily',
            'daily_rate' => $dailyRate,
            'assigned_site_id' => $site?->id,
            'status' => 'active',
            'joining_date' => '2026-01-10',
            'pf_status' => $statutoryStatus,
            'esic_status' => $statutoryStatus,
            'pt_status' => $statutoryStatus,
        ]);
    }

    public function test_high01_med01_med03_low01_low02_schema_indexes_and_idempotency_guards(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'two_factor_last_step'));
        $this->assertTrue(Schema::hasColumn('users', 'must_change_password'));
        $this->assertTrue(Schema::hasColumn('company_user', 'restrict_to_assigned_sites'));
        $this->assertTrue(Schema::hasColumn('roles', 'is_system'));
        $this->assertTrue(Schema::hasColumn('companies', 'signature_disk'));
        $this->assertTrue(Schema::hasColumn('workers', 'user_id'));
        $this->assertTrue(Schema::hasColumn('ra_bills', 'igst_amount'));
        $this->assertTrue(Schema::hasColumn('vendors', 'phone'));

        // MED-01: Two net-new composite indexes exist; 4 redundant duplicate index names do not exist
        $this->assertTrue(Schema::hasIndex('activity_logs', 'idx_act_logs_comp_action_created'));
        $this->assertTrue(Schema::hasIndex('purchase_orders', 'idx_po_comp_site_status'));
        $this->assertFalse(Schema::hasIndex('activity_logs', 'idx_act_logs_comp_site_created'));
        $this->assertFalse(Schema::hasIndex('daily_attendances', 'idx_daily_att_comp_site_date'));
        $this->assertFalse(Schema::hasIndex('daily_attendances', 'idx_daily_att_comp_worker_date'));
        $this->assertFalse(Schema::hasIndex('tasks', 'idx_tasks_comp_site_status'));

        // LOW-02 & MED-03: Composite tenant-unique constraints exist on vendors, clients, and workers.user_id
        $this->assertTrue(Schema::hasIndex('vendors', 'vendors_company_code_unique'));
        $this->assertTrue(Schema::hasIndex('clients', 'clients_company_code_unique'));
        $this->assertTrue(Schema::hasIndex('workers', 'workers_company_user_unique'));
    }

    public function test_high02_payroll_and_bank_csv_authorization_site_scope_and_customized_roles(): void
    {
        $workerA = $this->makeWorker('EMP-SIRL-01', 'Nitin', $this->siteA, 900);
        $workerB = $this->makeWorker('EMP-SIRL-02', 'Suresh', $this->siteB, 950);

        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $workerA->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'worked_hours' => 8,
            'marked_by' => $this->admin1->id,
        ]);
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteB->id,
            'worker_id' => $workerB->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'worked_hours' => 8,
            'marked_by' => $this->admin1->id,
        ]);

        // Create Site A, Site B, and Company-Wide (site_id = null) payroll periods
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Site A Oct 2026',
                'site_id' => $this->siteA->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Site B Oct 2026',
                'site_id' => $this->siteB->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Company-Wide Oct 2026',
                'site_id' => null,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $periodSiteA = PayrollPeriod::where('title', 'Site A Oct 2026')->firstOrFail();
        $periodSiteB = PayrollPeriod::where('title', 'Site B Oct 2026')->firstOrFail();
        $periodAllSites = PayrollPeriod::where('title', 'Company-Wide Oct 2026')->firstOrFail();
        $entryA = PayrollEntry::where('payroll_period_id', $periodSiteA->id)->firstOrFail();
        $entryAll = PayrollEntry::where('payroll_period_id', $periodAllSites->id)->firstOrFail();

        // 1. Site Supervisor has 403 on all payroll routes and does NOT see Payroll link in sidebar
        $dashboardHtml = $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString(route('payroll.index'), (string) $dashboardHtml);

        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.index'))
            ->assertForbidden();
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.show', $periodSiteA))
            ->assertForbidden();
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.payslip', [$periodSiteA, $entryA]))
            ->assertForbidden();
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.payslip_pdf', [$periodSiteA, $entryA]))
            ->assertForbidden();
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.export_payout', $periodSiteA))
            ->assertForbidden();

        // 2. Site-restricted Site Manager A can view Site A period + CSV, gets 403 on Site B AND Company-Wide (site_id = NULL)
        $indexRes = $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.index'))
            ->assertOk();
        $visibleIds = $indexRes->viewData('periods')->pluck('id')->all();
        $this->assertContains($periodSiteA->id, $visibleIds);
        $this->assertNotContains($periodSiteB->id, $visibleIds);
        $this->assertNotContains($periodAllSites->id, $visibleIds);

        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.show', $periodSiteA))
            ->assertOk();
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.export_payout', $periodSiteA))
            ->assertOk();

        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.show', $periodSiteB))
            ->assertForbidden();
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.show', $periodAllSites))
            ->assertForbidden();
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.payslip', [$periodAllSites, $entryAll]))
            ->assertForbidden();
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.payslip_pdf', [$periodAllSites, $entryAll]))
            ->assertForbidden();
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.export_payout', $periodAllSites))
            ->assertForbidden();

        // 3. Unrestricted Company Admin and Office Staff CAN view and export Company-Wide period
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.export_payout', $periodAllSites))
            ->assertOk();
        $this->actingAs($this->officeStaff)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.export_payout', $periodAllSites))
            ->assertOk();

        // 4. Explicit per-user deny override blocks Office Staff on payroll UI and Bank CSV export
        $deniedOffice = $this->makeUser('Denied Office', 'denied.office@sirl.test', $this->roles['office_staff'], false, [], [
            'payroll.view' => 'deny',
        ]);
        $this->actingAs($deniedOffice)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.index'))
            ->assertForbidden();
        $this->actingAs($deniedOffice)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.export_payout', $periodSiteA))
            ->assertForbidden();

        // 5. Customized system role (__customized__) and custom role (is_system = false) without payroll.view DO NOT bypass via fallbackRoles
        $this->roles['site_manager']->update([
            'permissions' => ['__customized__', 'dashboard.view', 'workers.view'],
        ]);
        $customizedMgr = $this->makeUser('Customized SM', 'custom.sm@sirl.test', $this->roles['site_manager'], true, [$this->siteA->id]);
        $this->actingAs($customizedMgr)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.index'))
            ->assertForbidden();

        $customAuditorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Field Clerk',
            'slug' => 'field_clerk',
            'permissions' => ['dashboard.view', 'workers.view'],
            'is_system' => false,
            'is_active' => true,
        ]);
        $customClerk = $this->makeUser('Field Clerk', 'clerk@sirl.test', $customAuditorRole, false);
        $this->actingAs($customClerk)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.index'))
            ->assertForbidden();
    }

    public function test_high03_worker_user_link_permission_admin_lockout_and_atomic_deactivation_rollback(): void
    {
        // 1. Site Manager without users.edit sees empty linkableUsers and gets 403 attempting to link user_id
        $createRes = $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('workforce.create'))
            ->assertOk();
        $this->assertCount(0, $createRes->viewData('linkableUsers'));

        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('workforce.store'), [
                'first_name' => 'Unpermitted',
                'last_name' => 'Link',
                'worker_type' => 'employee',
                'trade_designation' => 'Engineer',
                'daily_rate' => 1000,
                'assigned_site_id' => $this->siteA->id,
                'user_id' => $this->supervisorA->id,
            ])
            ->assertForbidden();

        // 2. Self-deactivation guard: Admin 1 cannot mark a worker linked to Admin 1's own login as inactive
        $workerAdmin1 = $this->makeWorker('EMP-ADM-01', 'AdminOne', $this->siteA, 1500, 'not_applicable', $this->admin1->id);

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->from(route('workforce.edit', $workerAdmin1))
            ->put(route('workforce.update', $workerAdmin1), [
                'first_name' => 'AdminOne',
                'last_name' => 'Kulkarni',
                'worker_type' => 'employee',
                'trade_designation' => 'Director',
                'daily_rate' => 1500,
                'assigned_site_id' => $this->siteA->id,
                'status' => 'inactive',
                'user_id' => $this->admin1->id,
            ])
            ->assertRedirect(route('workforce.edit', $workerAdmin1))
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $workerAdmin1->fresh()->status);
        $this->assertTrue((bool) $this->admin1->fresh()->is_active);

        // 3. Senior Manager cannot deactivate a worker linked to Admin 2 (403)
        $workerAdmin2 = $this->makeWorker('EMP-ADM-02', 'AdminTwo', $this->siteA, 1500, 'not_applicable', $this->admin2->id);

        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('workforce.update', $workerAdmin2), [
                'first_name' => 'AdminTwo',
                'last_name' => 'Kulkarni',
                'worker_type' => 'employee',
                'trade_designation' => 'Director',
                'daily_rate' => 1500,
                'assigned_site_id' => $this->siteA->id,
                'status' => 'terminated',
                'user_id' => $this->admin2->id,
            ])
            ->assertForbidden();
        $this->assertSame('active', $workerAdmin2->fresh()->status);

        // 4. Simulated mid-transaction failure rolls back workers.status atomically (zero partial state)
        Event::listen('workforce.syncing_linked_user_deactivation', function () {
            throw new RuntimeException('Simulated mid-transaction failure');
        });

        try {
            $this->actingAs($this->admin1)
                ->withSession(['active_company_id' => $this->company->id])
                ->put(route('workforce.update', $workerAdmin2), [
                    'first_name' => 'AdminTwo',
                    'last_name' => 'Kulkarni',
                    'worker_type' => 'employee',
                    'trade_designation' => 'Director',
                    'daily_rate' => 1500,
                    'assigned_site_id' => $this->siteA->id,
                    'status' => 'terminated',
                    'user_id' => $this->admin2->id,
                ]);
        } catch (RuntimeException) {
            // Expected exception handled by Laravel test client or caught here
        } finally {
            Event::forget('workforce.syncing_linked_user_deactivation');
        }

        $this->assertSame('active', $workerAdmin2->fresh()->status);
        $this->assertTrue((bool) $this->admin2->fresh()->is_active);

        // 5. Admin 1 deactivating worker linked to Admin 2 succeeds atomically when Admin 1 remains active
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('workforce.update', $workerAdmin2), [
                'first_name' => 'AdminTwo',
                'last_name' => 'Kulkarni',
                'worker_type' => 'employee',
                'trade_designation' => 'Director',
                'daily_rate' => 1500,
                'assigned_site_id' => $this->siteA->id,
                'status' => 'terminated',
                'user_id' => $this->admin2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('terminated', $workerAdmin2->fresh()->status);
        $this->assertFalse((bool) $this->admin2->fresh()->is_active);
        $pivotStatus = DB::table('company_user')
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->admin2->id)
            ->value('status');
        $this->assertSame('inactive', $pivotStatus);
        $this->assertTrue(AuditLog::where('action', 'worker.linked_user_deactivated')->exists());

        // 6. Last Active Company Admin lockout guard: Admin 2 is now inactive, so Admin 1 is the sole active Company Admin
        $this->assertTrue($this->admin1->fresh()->isLastActiveCompanyAdmin($this->company->id));
        $lastAdminFail = $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->from(route('workforce.edit', $workerAdmin1))
            ->put(route('workforce.update', $workerAdmin1), [
                'first_name' => 'AdminOne',
                'last_name' => 'Kulkarni',
                'worker_type' => 'employee',
                'trade_designation' => 'Director',
                'daily_rate' => 1500,
                'assigned_site_id' => $this->siteA->id,
                'status' => 'terminated',
                'user_id' => $this->admin1->id,
            ]);

        $lastAdminFail->assertRedirect(route('workforce.edit', $workerAdmin1));
        $lastAdminFail->assertSessionHasErrors('status');
        $this->assertStringContainsString('last active Company Administrator', session('errors')->first('status'));
        $this->assertSame('active', $workerAdmin1->fresh()->status);
        $this->assertTrue((bool) $this->admin1->fresh()->is_active);

        // 7. Unlinked ordinary worker deactivation by Site Manager succeeds normally
        $ordinaryWorker = $this->makeWorker('WRK-ORD-01', 'Ganesh', $this->siteA, 750, 'not_applicable', null);
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('workforce.update', $ordinaryWorker), [
                'first_name' => 'Ganesh',
                'last_name' => 'Kulkarni',
                'worker_type' => 'employee',
                'trade_designation' => 'Helper',
                'daily_rate' => 750,
                'assigned_site_id' => $this->siteA->id,
                'status' => 'inactive',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $ordinaryWorker->fresh()->status);
    }

    public function test_high04_unconfigured_statutory_blocks_locking_until_configured_and_preserves_historical_snapshot(): void
    {
        $unconfiguredWorker = $this->makeWorker('EMP-STAT-01', 'Prakash', $this->siteA, 1000, 'unconfigured');

        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $unconfiguredWorker->id,
            'attendance_date' => '2026-10-10',
            'status' => 'present',
            'worked_hours' => 8,
            'marked_by' => $this->admin1->id,
        ]);

        // Admin 1 prepares the payroll period
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Statutory Gate Oct 2026',
                'site_id' => $this->siteA->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $period = PayrollPeriod::where('title', 'Statutory Gate Oct 2026')->firstOrFail();

        // Independent approver (Senior Manager) attempts to lock while worker is unconfigured -> blocked!
        $lockFail = $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->from(route('payroll.show', $period))
            ->post(route('payroll.lock', $period));

        $lockFail->assertRedirect(route('payroll.show', $period));
        $lockFail->assertSessionHasErrors('statutory');
        $errorText = session('errors')->first('statutory');
        $this->assertStringContainsString('EMP-STAT-01', $errorText);
        $this->assertSame('calculated', $period->fresh()->status);

        // Configure worker explicitly as Not Applicable for ESIC/PT and Applicable for PF (12%)
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('workforce.statutory.update', $unconfiguredWorker), [
                'pf_status' => 'applicable',
                'pf_employee_rate' => 12,
                'pf_employer_rate' => 12,
                'pf_wage_ceiling' => 15000,
                'uan_number' => '100999888777',
                'esic_status' => 'not_applicable',
                'pt_status' => 'not_applicable',
            ])
            ->assertSessionHasNoErrors();

        // Admin 1 recalculates, then Senior Manager approves & locks -> succeeds!
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.recalculate', $period))
            ->assertRedirect();

        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.lock', $period))
            ->assertSessionHasNoErrors();

        $this->assertSame('locked', $period->fresh()->status);

        $lockedEntry = PayrollEntry::where('payroll_period_id', $period->id)->firstOrFail();
        $this->assertEquals(120.00, (float) $lockedEntry->pf_employee_amount);

        // Changing worker statutory profile afterwards does NOT mutate historical locked entry snapshot
        $unconfiguredWorker->update(['pf_status' => 'not_applicable']);
        $this->assertEquals(120.00, (float) $lockedEntry->fresh()->pf_employee_amount);
        $this->assertSame('applicable', $lockedEntry->fresh()->statutory_snapshot['pf']['status']);
    }

    public function test_med02_inventory_and_procurement_max_length_validation_matches_mysql_schema(): void
    {
        // Warehouse code > 30 rejected, == 30 accepted
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('inventory.warehouses.store'), [
                'name' => 'Central Store',
                'code' => str_repeat('W', 31),
                'location_type' => 'site_yard',
                'site_id' => $this->siteA->id,
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('inventory.warehouses.store'), [
                'name' => 'Central Store',
                'code' => str_repeat('W', 30),
                'location_type' => 'site_yard',
                'site_id' => $this->siteA->id,
            ])
            ->assertSessionHasNoErrors();

        // Category code > 30 rejected, == 30 accepted
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('inventory.categories.store'), [
                'name' => 'Aggregates',
                'code' => str_repeat('C', 31),
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('inventory.categories.store'), [
                'name' => 'Aggregates',
                'code' => str_repeat('C', 30),
            ])
            ->assertSessionHasNoErrors();

        $category = MaterialCategory::where('company_id', $this->company->id)->firstOrFail();

        // Material code > 30 or unit_of_measure > 20 rejected; exact max lengths (30 / 20) accepted
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('inventory.materials.store'), [
                'category_id' => $category->id,
                'name' => 'Crushed Sand',
                'code' => str_repeat('M', 31),
                'unit_of_measure' => str_repeat('U', 21),
                'minimum_stock_level' => 10,
                'standard_unit_cost' => 350,
            ])
            ->assertSessionHasErrors(['code', 'unit_of_measure']);

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('inventory.materials.store'), [
                'category_id' => $category->id,
                'name' => 'OPC 53 Cement',
                'code' => str_repeat('M', 30),
                'unit_of_measure' => str_repeat('U', 20),
                'minimum_stock_level' => 10,
                'standard_unit_cost' => 350,
            ])
            ->assertSessionHasNoErrors();

        $material = Material::where('company_id', $this->company->id)->firstOrFail();

        // Vendor phone > 30 rejected
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('procurement.vendors.store'), [
                'name' => 'Shree Steel Traders',
                'vendor_type' => 'material_supplier',
                'phone' => str_repeat('9', 31),
            ])
            ->assertSessionHasErrors('phone');

        // Purchase Order supplier_phone > 30 rejected
        $warehouse = Warehouse::where('company_id', $this->company->id)->firstOrFail();
        $pr = PurchaseRequest::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'warehouse_id' => $warehouse->id,
            'request_number' => 'PR-SIRL-001',
            'status' => 'approved',
            'requested_by' => $this->siteManagerA->id,
            'approved_by' => $this->admin1->id,
            'approved_at' => now(),
        ]);
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $material->id,
            'quantity_requested' => 100,
        ]);

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('procurement.store_po', $pr), [
                'supplier_name' => 'Konkan Cement Agency',
                'supplier_phone' => str_repeat('8', 31),
                'order_date' => '2026-10-12',
                'items' => [
                    $item->id => ['unit_price' => 380],
                ],
            ])
            ->assertSessionHasErrors('supplier_phone');
    }

    public function test_med04_leave_type_and_leave_request_mutations_enforce_explicit_rbac_and_denies(): void
    {
        $worker = $this->makeWorker('EMP-LV-01', 'Mahesh', $this->siteA);
        $leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Casual Leave',
            'code' => 'CL',
            'is_paid' => true,
            'annual_quota' => 12,
        ]);

        // Office staff lacks attendance.mark / attendance.record -> 403 on storeLeaveRequest
        $this->actingAs($this->officeStaff)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('attendance.leaves.store'), [
                'worker_id' => $worker->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
                'reason' => 'Personal work',
            ])
            ->assertForbidden();

        // Supervisor with explicit deny on attendance.mark & attendance.record -> 403 on storeLeaveRequest
        $deniedSupervisor = $this->makeUser(
            'Denied Supervisor',
            'denied.sup@sirl.test',
            $this->roles['site_supervisor'],
            true,
            [$this->siteA->id],
            ['attendance.mark' => 'deny', 'attendance.record' => 'deny']
        );
        $this->actingAs($deniedSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('attendance.leaves.store'), [
                'worker_id' => $worker->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-10-15',
                'end_date' => '2026-10-15',
            ])
            ->assertForbidden();

        // Senior Manager with explicit deny on attendance.manage -> 403 on storeLeaveType
        $deniedSenior = $this->makeUser(
            'Denied Senior Attendance',
            'denied.senior.att@sirl.test',
            $this->roles['senior_manager'],
            false,
            [],
            ['attendance.manage' => 'deny']
        );
        $this->actingAs($deniedSenior)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('attendance.leave_types.store'), [
                'name' => 'Sick Leave',
                'code' => 'SL',
                'is_paid' => true,
                'annual_quota' => 7,
            ])
            ->assertForbidden();
    }

    public function test_med05_payroll_approval_enforces_independent_maker_checker(): void
    {
        $worker = $this->makeWorker('EMP-MC-01', 'Santosh', $this->siteA, 900, 'not_applicable');
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $worker->id,
            'attendance_date' => '2026-10-08',
            'status' => 'present',
            'worked_hours' => 8,
            'marked_by' => $this->admin1->id,
        ]);

        // Admin 1 prepares the payroll period
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Maker Checker Oct 2026',
                'site_id' => $this->siteA->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $period = PayrollPeriod::where('title', 'Maker Checker Oct 2026')->firstOrFail();

        // Admin 1 CANNOT self-approve via HTTP controller (403)
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.lock', $period))
            ->assertForbidden();

        // Direct service call also enforces maker-checker
        $service = app(PayrollCalculationService::class);
        try {
            $service->approveAndLockPeriod($period->fresh(), $this->admin1);
            $this->fail('Expected ValidationException for maker-checker self-approval');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('maker_checker', $e->errors());
        }

        // Senior Manager adjusts an entry -> Senior Manager becomes last modifier (calculated_by) and cannot self-approve
        $entry = PayrollEntry::where('payroll_period_id', $period->id)->firstOrFail();
        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.entries.adjust', [$period, $entry]), [
                'bonus_amount' => 100,
                'other_deduction' => 0,
                'deduction_notes' => 'Attendance incentive',
            ])
            ->assertRedirect();

        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.lock', $period))
            ->assertForbidden();

        // Independent approver (Admin 2) CAN approve and lock
        $this->actingAs($this->admin2)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.lock', $period))
            ->assertSessionHasNoErrors();

        $this->assertSame('locked', $period->fresh()->status);
    }

    public function test_med06_low_gross_pt_capping_prevents_negative_net_pay_and_excess_manual_deduction(): void
    {
        $worker = Worker::create([
            'company_id' => $this->company->id,
            'code' => 'EMP-PT-CAP',
            'first_name' => 'Rohan',
            'last_name' => 'More',
            'worker_type' => 'employee',
            'trade_designation' => 'Helper',
            'pay_type' => 'daily',
            'daily_rate' => 300,
            'assigned_site_id' => $this->siteA->id,
            'status' => 'active',
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'applicable',
            'pt_monthly_amount' => 200.00,
        ]);

        // 1 half-day = ₹150 gross pay, while configured PT is ₹200
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $worker->id,
            'attendance_date' => '2026-10-03',
            'status' => 'half_day',
            'worked_hours' => 4,
            'marked_by' => $this->admin1->id,
        ]);

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'Low Gross PT Cap Oct 2026',
                'site_id' => $this->siteA->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $period = PayrollPeriod::where('title', 'Low Gross PT Cap Oct 2026')->firstOrFail();
        $entry = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $worker->id)->firstOrFail();

        $this->assertEquals(150.00, (float) $entry->gross_pay);
        $this->assertEquals(150.00, (float) $entry->pt_amount);
        $this->assertEquals(0.00, (float) $entry->net_pay);
        $this->assertSame('capped_to_available_gross', $entry->statutory_snapshot['pt']['reason']);
        $this->assertEquals(50.00, (float) $entry->statutory_snapshot['pt']['unrecovered_shortfall']);

        // Period header reconciles exactly: total_gross_amount - total_deductions == total_net_amount
        $reconciledNet = round((float) $period->total_gross_amount - (float) $period->total_deductions, 2);
        $this->assertEquals((float) $period->total_net_amount, $reconciledNet);

        // Attempting manual other_deduction > remaining gross (0.00) fails cleanly with validation error
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.entries.adjust', [$period, $entry]), [
                'bonus_amount' => 0,
                'other_deduction' => 25.00,
                'deduction_notes' => 'Shoe recovery',
            ])
            ->assertSessionHasErrors('other_deduction');
    }

    public function test_med07_vault_storage_traversal_and_failure_guards_work_order_cleanup_and_company_signature_audit(): void
    {
        Storage::fake('local');
        Storage::fake('vault');

        // 1. VaultStorage blocks path traversal
        try {
            VaultStorage::put('../escape/evil.txt', 'payload');
            $this->fail('Expected RuntimeException for path traversal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('invalid vault storage', strtolower($e->getMessage()));
        }

        // 2. WorkOrderUploadService cleans up stored PDF if DB creation throws an exception
        $pdfContent = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";
        $pdfFile = UploadedFile::fake()->createWithContent('service-order.pdf', $pdfContent);

        WorkOrderUpload::creating(function () {
            throw new RuntimeException('Simulated DB insert failure during upload');
        });

        $uploadService = app(WorkOrderUploadService::class);
        try {
            $uploadService->uploadAndExtract($this->company->id, $pdfFile, $this->admin1);
            $this->fail('Expected RuntimeException from WorkOrderUpload::creating');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Simulated DB insert failure', $e->getMessage());
        } finally {
            WorkOrderUpload::flushEventListeners();
            WorkOrderUpload::boot();
        }

        $this->assertCount(0, Storage::disk('vault')->allFiles("work-order-uploads/{$this->company->id}"));
        $this->assertCount(0, Storage::disk('local')->allFiles("work-order-uploads/{$this->company->id}"));

        // 3. CompanySettingsController stores signature via VaultStorage, records signature_disk, and populates old_values in AuditLog
        $sigImage = UploadedFile::fake()->image('sign.png', 240, 80);

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->put(route('admin.company.update'), [
                'name' => 'Sahyadri Infra Updated Ltd',
                'invoice_prefix' => 'SIRL-TAX',
                'invoice_starting_number' => 101,
                'invoice_rounding_mode' => 'rupee',
                'signature' => $sigImage,
            ])
            ->assertSessionHasNoErrors();

        $freshCompany = $this->company->fresh();
        $this->assertNotNull($freshCompany->signature_path);
        $this->assertSame(VaultStorage::activeDiskName(), $freshCompany->signature_disk);
        $this->assertTrue(VaultStorage::exists($freshCompany->signature_path, $freshCompany->signature_disk));

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('admin.company.signature'))
            ->assertOk();

        $audit = AuditLog::where('action', 'company.settings_updated')->latest('id')->firstOrFail();
        $this->assertIsArray($audit->old_values);
        $this->assertSame('Sahyadri Infra Remediation Ltd', $audit->old_values['name']);
        $this->assertSame('Sahyadri Infra Updated Ltd', $audit->new_values['name']);
    }

    public function test_low02_low03_low05_atomic_vendor_client_codes_switch_company_redirect_and_payroll_csv_sanitization(): void
    {
        // 1. LOW-02: Blank vendor & client codes generate sequential VEND-001/002 and CLI-001/002 via worker_sequences
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('procurement.vendors.store'), ['name' => 'Vendor Alpha', 'vendor_type' => 'material_supplier'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('procurement.vendors.store'), ['name' => 'Vendor Beta', 'vendor_type' => 'equipment_rental'])
            ->assertSessionHasNoErrors();

        $vendorCodes = Vendor::where('company_id', $this->company->id)->orderBy('id')->pluck('code')->all();
        $this->assertSame(['VEND-001', 'VEND-002'], $vendorCodes);

        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('clients.store'), ['name' => 'Client One'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('clients.store'), ['name' => 'Client Two'])
            ->assertSessionHasNoErrors();

        $clientCodes = Client::where('company_id', $this->company->id)->orderBy('id')->pluck('code')->all();
        $this->assertSame(['CLI-001', 'CLI-002'], $clientCodes);

        // 2. LOW-03: switchCompany redirects cleanly to route('dashboard') and syncs both session keys
        $company2 = Company::create([
            'name' => 'Deccan Bridges Ltd',
            'code' => 'DBL',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);
        $role2 = Role::create([
            'company_id' => $company2->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
            'is_system' => true,
            'is_active' => true,
        ]);
        $this->admin1->companies()->attach($company2->id, [
            'role_id' => $role2->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        $switchRes = $this->actingAs($this->admin1)
            ->from(route('projects.show', $this->project))
            ->post(route('switch_company'), ['company_id' => $company2->id]);

        $switchRes->assertRedirect(route('dashboard'));
        $switchRes->assertSessionHas('active_company_id', $company2->id);
        $switchRes->assertSessionHas('current_company_id', $company2->id);

        // 3. LOW-05: AttendanceController::exportPayrollCsv prefixes formula-triggering cells with single quote
        TenantContext::setCompanyId($this->company->id);
        $formulaWorker = Worker::create([
            'company_id' => $this->company->id,
            'code' => '=HYPERLINK("http://evil.test")',
            'first_name' => '@SUM(1+1)',
            'last_name' => 'Test',
            'worker_type' => 'direct_labour',
            'trade_designation' => '+CMD|calc',
            'pay_type' => 'daily',
            'daily_rate' => 600,
            'assigned_site_id' => $this->siteA->id,
            'status' => 'active',
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'not_applicable',
        ]);
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'worker_id' => $formulaWorker->id,
            'attendance_date' => '2026-10-18',
            'status' => 'present',
            'worked_hours' => 8,
            'marked_by' => $this->admin1->id,
        ]);

        $csvRes = $this->actingAs($this->admin1)
            ->withSession(['active_company_id' => $this->company->id, 'current_company_id' => $this->company->id])
            ->get(route('attendance.export_payroll', [
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'site_id' => $this->siteA->id,
            ]));

        $csvRes->assertOk();
        $csvContent = $csvRes->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(", $csvContent);
        $this->assertStringContainsString("'@SUM(1+1)", $csvContent);
        $this->assertStringContainsString("'+CMD|calc", $csvContent);
    }
}
