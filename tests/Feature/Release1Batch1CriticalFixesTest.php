<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Release1Batch1CriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected Site $siteA1;

    protected Site $siteA2;

    protected Site $siteB1;

    protected User $adminA;

    protected User $seniorA;

    protected User $officeStaffA;

    protected Role $adminRoleA;

    protected Role $seniorRoleA;

    protected Role $officeStaffRoleA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Alpha Civil Infrastructure',
            'code' => 'ALPHA',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Bravo Earthworks Pvt Ltd',
            'code' => 'BRAVO',
            'is_active' => true,
        ]);

        $this->adminRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->seniorRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => PermissionCatalog::defaultRolePermissions()['senior_manager'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->officeStaffRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Office Staff',
            'slug' => 'office_staff',
            'permissions' => PermissionCatalog::defaultRolePermissions()['office_staff'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->adminA = $this->createUserInCompany('Admin Alpha', 'admin@alpha.test', $this->companyA, $this->adminRoleA);
        $this->seniorA = $this->createUserInCompany('Senior Alpha', 'senior@alpha.test', $this->companyA, $this->seniorRoleA);
        $this->officeStaffA = $this->createUserInCompany('Office Alpha', 'office@alpha.test', $this->companyA, $this->officeStaffRoleA);

        TenantContext::setCompanyId($this->companyA->id);
        $projectA = Project::create([
            'company_id' => $this->companyA->id,
            'code' => 'PRJ-INIT-A',
            'name' => 'Alpha Master Project',
            'status' => 'active',
        ]);
        $this->siteA1 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projectA->id,
            'name' => 'Alpha Highway Package 1',
            'code' => 'SITE-A1',
            'status' => 'active',
        ]);
        $this->siteA2 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projectA->id,
            'name' => 'Alpha Bridge Package 2',
            'code' => 'SITE-A2',
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyB->id);
        $projectB = Project::create([
            'company_id' => $this->companyB->id,
            'code' => 'PRJ-INIT-B',
            'name' => 'Bravo Master Project',
            'status' => 'active',
        ]);
        $this->siteB1 = Site::create([
            'company_id' => $this->companyB->id,
            'project_id' => $projectB->id,
            'name' => 'Bravo Secret Plant Site',
            'code' => 'SITE-B1',
            'status' => 'active',
        ]);
        TenantContext::reset();
    }

    protected function createUserInCompany(
        string $name,
        string $email,
        Company $company,
        Role $role,
        array $overrides = [],
        bool $restrictSites = false
    ): User {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('ValidPass#2026'),
            'is_active' => true,
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
            'permission_overrides' => empty($overrides) ? null : json_encode($overrides),
            'restrict_to_assigned_sites' => $restrictSites,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_bl01_audit_log_enforces_company_scope_fails_closed_and_redacts_secrets(): void
    {
        TenantContext::reset();
        $this->assertSame(0, AuditLog::forCompany()->count());

        $alphaLog = AuditLog::record(
            'worker.credentials_updated',
            null,
            ['password' => 'OldPlainSecret123', 'bank_account_number' => '998877665544'],
            [
                'password' => 'NewPlainSecret456',
                'pin_hash' => '$2y$10$hashvalue',
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
                'bank_account_no' => '112233445566',
                'nested' => ['kiosk_token' => 'kiosk-secret-xyz', 'safe_note' => 'visible'],
            ],
            'Alpha internal security update',
            $this->adminA->id,
            $this->companyA->id
        );

        $bravoLog = AuditLog::record(
            'project.secret_bravo_action',
            null,
            null,
            ['note' => 'Bravo confidential'],
            'Bravo confidential audit entry',
            null,
            $this->companyB->id
        );

        $this->assertNotNull($alphaLog);
        $this->assertNotNull($bravoLog);
        $this->assertSame('[REDACTED]', $alphaLog->old_values['password']);
        $this->assertSame('[REDACTED]', $alphaLog->old_values['bank_account_number']);
        $this->assertSame('[REDACTED]', $alphaLog->new_values['password']);
        $this->assertSame('[REDACTED]', $alphaLog->new_values['pin_hash']);
        $this->assertSame('[REDACTED]', $alphaLog->new_values['two_factor_secret']);
        $this->assertSame('[REDACTED]', $alphaLog->new_values['bank_account_no']);
        $this->assertSame('[REDACTED]', $alphaLog->new_values['nested']['kiosk_token']);
        $this->assertSame('visible', $alphaLog->new_values['nested']['safe_note']);

        // Senior Manager on Company A has unrestricted site scope ($scopedSiteIds === null),
        // which previously allowed Company B AuditLog rows onto Company A's dashboard.
        $res = $this->actingAs($this->seniorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('dashboard'));

        $res->assertOk();
        $logsOnDashboard = $res->viewData('recentActivityLogs');
        $this->assertTrue($logsOnDashboard->contains('id', $alphaLog->id));
        $this->assertFalse($logsOnDashboard->contains('id', $bravoLog->id));
        $res->assertDontSee('project.secret_bravo_action');
    }

    public function test_bl02_dashboard_pending_goods_receipt_orders_matches_issued_and_partially_received_statuses(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $warehouse = Warehouse::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alpha Central Store',
            'code' => 'WH-A1',
            'location_type' => 'central_warehouse',
            'site_id' => $this->siteA1->id,
            'is_active' => true,
        ]);
        $cat = MaterialCategory::create(['company_id' => $this->companyA->id, 'name' => 'Cement', 'code' => 'CEM']);
        $mat = Material::create([
            'company_id' => $this->companyA->id,
            'category_id' => $cat->id,
            'name' => 'OPC 53 Cement',
            'code' => 'MAT-CEM-53',
            'unit_of_measure' => 'Bags',
            'is_active' => true,
        ]);

        $issuedPo = PurchaseOrder::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->siteA1->id,
            'warehouse_id' => $warehouse->id,
            'po_number' => 'PO-ALPHA-2026-001',
            'supplier_name' => 'UltraTech',
            'order_date' => now()->toDateString(),
            'total_amount' => 38000,
            'status' => 'issued',
            'created_by' => $this->adminA->id,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $issuedPo->id,
            'material_id' => $mat->id,
            'quantity_ordered' => 100,
            'unit_price' => 380,
            'quantity_received' => 0,
        ]);

        $receivedPo = PurchaseOrder::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->siteA1->id,
            'warehouse_id' => $warehouse->id,
            'po_number' => 'PO-ALPHA-2026-002',
            'supplier_name' => 'ACC Cement',
            'order_date' => now()->toDateString(),
            'total_amount' => 19000,
            'status' => 'received',
            'created_by' => $this->adminA->id,
        ]);

        $res = $this->actingAs($this->seniorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('dashboard'));

        $res->assertOk();
        $pendingOrders = $res->viewData('pendingGoodsReceiptOrders');
        $this->assertTrue($pendingOrders->contains('id', $issuedPo->id));
        $this->assertFalse($pendingOrders->contains('id', $receivedPo->id));
    }

    public function test_bl02_payroll_permissions_use_granular_catalog_keys_and_respect_explicit_denies(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $worker = Worker::create([
            'company_id' => $this->companyA->id,
            'code' => 'WRK-A001',
            'first_name' => 'Ramesh',
            'last_name' => 'Patil',
            'worker_type' => 'direct_labour',
            'pay_type' => 'monthly',
            'monthly_salary' => 18000,
            'daily_wage_rate' => 600,
            'assigned_site_id' => $this->siteA1->id,
            'status' => 'active',
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'not_applicable',
        ]);

        $accountantRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permissions' => PermissionCatalog::customRoleTemplates()['accountant']['permissions'],
            'is_system' => false,
            'is_active' => true,
        ]);
        $hrRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'HR Manager',
            'slug' => 'hr_manager',
            'permissions' => PermissionCatalog::customRoleTemplates()['hr_manager']['permissions'],
            'is_system' => false,
            'is_active' => true,
        ]);

        $accountant = $this->createUserInCompany('Accountant User', 'acct@alpha.test', $this->companyA, $accountantRole);
        $accountant->sites()->attach($this->siteA1->id);
        $hrManager = $this->createUserInCompany('HR User', 'hr@alpha.test', $this->companyA, $hrRole);
        $hrManager->sites()->attach($this->siteA1->id);
        $deniedSenior = $this->createUserInCompany(
            'Denied Senior',
            'denied.senior@alpha.test',
            $this->companyA,
            $this->seniorRoleA,
            ['payroll.approve' => 'deny']
        );

        // Accountant has payroll.view, payroll.create, payroll.edit (NOT payroll.approve)
        $this->actingAs($accountant)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('payroll.store'), [
                'title' => 'October 2026 Payroll',
                'site_id' => $this->siteA1->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->assertRedirect();

        $period = PayrollPeriod::where('company_id', $this->companyA->id)->firstOrFail();
        $entry = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $worker->id)->firstOrFail();

        // Accountant can adjust entry (payroll.edit)
        $this->actingAs($accountant)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('payroll.entries.adjust', [$period, $entry]), [
                'bonus_amount' => 500,
                'other_deduction' => 100,
                'deduction_notes' => 'Canteen',
            ])
            ->assertRedirect();

        // Accountant CANNOT approve & lock (lacks payroll.approve)
        $this->actingAs($accountant)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('payroll.lock', $period))
            ->assertForbidden();

        // Senior Manager with explicit deny on payroll.approve CANNOT lock
        $this->actingAs($deniedSenior)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('payroll.lock', $period))
            ->assertForbidden();

        // HR Manager with payroll.approve CAN approve & lock
        $this->actingAs($hrManager)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('payroll.lock', $period))
            ->assertRedirect();

        $this->assertSame('locked', $period->fresh()->status);
    }

    public function test_bl02_and_bl09_office_staff_custom_procurement_permission_and_pr_rejection_and_cancellation(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $cat = MaterialCategory::create(['company_id' => $this->companyA->id, 'name' => 'Steel', 'code' => 'STL']);
        $material = Material::create([
            'company_id' => $this->companyA->id,
            'category_id' => $cat->id,
            'name' => 'TMT Fe500D 16mm',
            'code' => 'MAT-TMT-16',
            'unit_of_measure' => 'MT',
            'is_active' => true,
        ]);

        // 1. Default Office Staff does NOT have procurement.request on frontend or backend
        $listRes = $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('procurement.requests'));
        $listRes->assertOk();
        $listRes->assertDontSee('+ New Purchase Request');

        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('procurement.create_request'))
            ->assertForbidden();

        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.store_request'), [
                'site_id' => $this->siteA1->id,
                'urgency' => 'normal',
                'items' => [['material_id' => $material->id, 'quantity' => 10]],
            ])
            ->assertForbidden();

        // 2. Office Staff granted per-user allow override for procurement.request CAN create PR on assigned site
        $authorizedClerk = $this->createUserInCompany(
            'Procurement Clerk',
            'clerk@alpha.test',
            $this->companyA,
            $this->officeStaffRoleA,
            ['procurement.request' => 'allow'],
            true
        );
        $authorizedClerk->sites()->attach($this->siteA1->id);

        // Wrong site blocked (403)
        $this->actingAs($authorizedClerk)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.store_request'), [
                'site_id' => $this->siteA2->id,
                'urgency' => 'normal',
                'items' => [['material_id' => $material->id, 'quantity' => 10]],
            ])
            ->assertForbidden();

        // Assigned site succeeds
        $this->actingAs($authorizedClerk)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.store_request'), [
                'site_id' => $this->siteA1->id,
                'urgency' => 'urgent',
                'items' => [['material_id' => $material->id, 'quantity' => 12, 'purpose_notes' => 'Slab reinforcement']],
            ])
            ->assertRedirect(route('procurement.requests'));

        $pr = PurchaseRequest::where('company_id', $this->companyA->id)->firstOrFail();

        // 3. Explicit deny wins even if role has procurement.request
        $siteMgrRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => PermissionCatalog::defaultRolePermissions()['site_manager'],
            'is_system' => true,
            'is_active' => true,
        ]);
        $deniedSiteMgr = $this->createUserInCompany(
            'Denied Site Manager',
            'denied.sm@alpha.test',
            $this->companyA,
            $siteMgrRole,
            ['procurement.request' => 'deny']
        );
        $deniedSiteMgr->sites()->attach($this->siteA1->id);

        $this->actingAs($deniedSiteMgr)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.store_request'), [
                'site_id' => $this->siteA1->id,
                'urgency' => 'normal',
                'items' => [['material_id' => $material->id, 'quantity' => 5]],
            ])
            ->assertForbidden();

        // 4. Non-self-approval / non-self-rejection enforced even if requester also holds senior_manager role
        $this->actingAs($this->seniorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.reject_request', $pr), ['rejection_reason' => ''])
            ->assertSessionHasErrors(['rejection_reason']);

        $this->actingAs($this->seniorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.reject_request', $pr), [
                'rejection_reason' => 'Excess steel already available at central yard.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $pr->fresh()->status);
        $this->assertSame('Excess steel already available at central yard.', $pr->fresh()->rejection_reason);
        $this->assertTrue(AuditLog::forCompany($this->companyA->id)->where('action', 'procurement.request.rejected')->where('subject_id', $pr->id)->exists());

        // Duplicate rejection or PO creation on rejected PR is blocked (403)
        $this->actingAs($this->seniorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.reject_request', $pr), ['rejection_reason' => 'Retry reject'])
            ->assertForbidden();

        $this->actingAs($this->seniorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.store_po', $pr), [
                'supplier_name' => 'Vendor',
                'unit_prices' => [$material->id => 55000],
            ])
            ->assertForbidden();

        // 5. Requester cancellation of pending PR & self-approval block
        $this->actingAs($this->adminA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.store_request'), [
                'site_id' => $this->siteA1->id,
                'urgency' => 'normal',
                'items' => [['material_id' => $material->id, 'quantity' => 4]],
            ])
            ->assertRedirect();

        $selfPr = PurchaseRequest::where('company_id', $this->companyA->id)->latest('id')->firstOrFail();

        // Admin cannot self-approve their own PR
        $this->actingAs($this->adminA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.approve_request', $selfPr))
            ->assertForbidden();

        // Requester can cancel their own pending PR
        $this->actingAs($this->adminA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('procurement.cancel_request', $selfPr), ['reason' => 'No longer needed'])
            ->assertRedirect();

        $this->assertSame('cancelled', $selfPr->fresh()->status);
    }

    public function test_bl14_project_activity_tab_displays_real_tenant_and_site_scoped_logs_or_honest_empty_state(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $project = Project::create([
            'company_id' => $this->companyA->id,
            'code' => 'PRJ-A01',
            'name' => 'Alpha Coastal Road',
            'status' => 'active',
        ]);
        $project->linkedSites()->attach([
            $this->siteA1->id => ['company_id' => $this->companyA->id],
            $this->siteA2->id => ['company_id' => $this->companyA->id],
        ]);

        // Empty state first
        $emptyRes = $this->actingAs($this->adminA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.show', ['project' => $project, 'tab' => 'activity']));
        $emptyRes->assertOk();
        $emptyRes->assertSee('No recent activity logs recorded for this project.');

        // Record logs on Site A1, Site A2, and Company B
        AuditLog::record('milestone.updated', $project, null, ['site_id' => $this->siteA1->id], 'Completed pile cap on Site A1', $this->adminA->id, $this->companyA->id);
        AuditLog::record('site.inspection_logged', null, null, ['site_id' => $this->siteA2->id, 'project_id' => $project->id], 'Bridge girder check on Site A2', $this->adminA->id, $this->companyA->id);
        AuditLog::record('project.foreign_log', null, null, ['project_id' => $project->id], 'Cross-tenant spoofed project id', null, $this->companyB->id);

        // Site-restricted Site Manager assigned ONLY to Site A1 sees Site A1 log, NOT Site A2 or Company B log
        $smRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager_custom',
            'permissions' => ['projects.view', 'sites.view'],
            'is_system' => false,
            'is_active' => true,
        ]);
        $restrictedSm = $this->createUserInCompany('Restricted SM', 'rsm@alpha.test', $this->companyA, $smRole, [], true);
        $restrictedSm->sites()->attach($this->siteA1->id);

        $scopedRes = $this->actingAs($restrictedSm)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.show', ['project' => $project, 'tab' => 'activity']));

        $scopedRes->assertOk();
        $scopedRes->assertSee('Completed pile cap on Site A1');
        $scopedRes->assertDontSee('Bridge girder check on Site A2');
        $scopedRes->assertDontSee('Cross-tenant spoofed project id');
    }
}
