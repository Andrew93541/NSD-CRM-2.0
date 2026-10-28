<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Services\Procurement\InventoryService;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class TenantForgedSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected Role $adminRoleA;
    protected Role $adminRoleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create(['name' => 'Alpha Constructions', 'code' => 'ALPHA', 'is_active' => true]);
        $this->companyB = Company::create(['name' => 'Beta Earthworks', 'code' => 'BETA', 'is_active' => true]);

        $this->adminRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->adminRoleB = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->userA = User::create([
            'name' => 'User Alpha',
            'email' => 'alpha@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->userA->companies()->attach($this->companyA->id, ['role_id' => $this->adminRoleA->id, 'status' => 'active', 'is_primary' => true]);
    }

    public function test_forged_company_id_in_session_is_rejected_and_purged(): void
    {
        // Attacker manually tampers session to point to Company B which user does not belong to
        $response = $this->actingAs($this->userA)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->get('/dashboard');

        $response->assertStatus(200);

        // Session must have been purged of forged ID and set to legitimate Company A
        $this->assertEquals($this->companyA->id, session('current_company_id'));
        $this->assertEquals($this->companyA->id, TenantContext::getCompanyId());
    }

    public function test_inactive_company_membership_is_rejected(): void
    {
        // User suspended in Company B
        $this->userA->companies()->attach($this->companyB->id, ['role_id' => $this->adminRoleB->id, 'status' => 'suspended', 'is_primary' => false]);

        // Attempting to switch to suspended company must be rejected with 403 Forbidden
        $response = $this->actingAs($this->userA)->post('/switch-company', [
            'company_id' => $this->companyB->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_with_no_active_company_membership_fails_closed(): void
    {
        $suspendedUser = User::create([
            'name' => 'Suspended Staff',
            'email' => 'suspended@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $suspendedUser->companies()->attach($this->companyA->id, ['role_id' => $this->adminRoleA->id, 'status' => 'inactive']);

        $response = $this->actingAs($suspendedUser)->get('/dashboard');
        $response->assertStatus(403);
    }

    public function test_cross_company_site_access_is_denied(): void
    {
        TenantContext::setCompanyId($this->companyB->id);
        $projectB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Beta Project',
            'code' => 'BP-1',
            'status' => 'active',
        ]);
        $siteB = Site::create([
            'company_id' => $this->companyB->id,
            'project_id' => $projectB->id,
            'name' => 'Site in Company B',
            'code' => 'SB-1',
            'status' => 'active',
            'supervisor_id' => $this->userA->id, // User is direct supervisor in DB
        ]);

        // When User A operates in Company A, isAssignedToSite must strictly return false for Site B
        TenantContext::setCompanyId($this->companyA->id);
        $this->actingAs($this->userA);
        $this->assertFalse($this->userA->isAssignedToSite($siteB));
    }

    public function test_cross_company_work_order_cannot_be_viewed(): void
    {
        TenantContext::setCompanyId($this->companyB->id);
        $projectB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Beta Project',
            'code' => 'BP-1',
            'status' => 'active',
        ]);
        $woB = WorkOrder::create([
            'company_id' => $this->companyB->id,
            'project_id' => $projectB->id,
            'order_number' => 'WO-BETA-001',
            'title' => 'Beta Work Order',
            'status' => 'submitted',
            'created_by' => $this->userA->id,
        ]);

        // User A in Company A attempts to view Company B's work order
        TenantContext::setCompanyId($this->companyA->id);
        $response = $this->actingAs($this->userA)->get("/work-orders/{$woB->id}");

        // Due to global tenant scoping, WorkOrder is not found in Company A's scope (404)
        $response->assertStatus(404);
    }

    public function test_worker_create_edit_and_update_lifecycle(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $projA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alpha Project',
            'code' => 'AP-1',
            'status' => 'active',
        ]);

        $siteA = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projA->id,
            'name' => 'Alpha Site 1',
            'code' => 'AS-1',
            'status' => 'active',
        ]);

        // 1. Create Worker
        $createRes = $this->actingAs($this->userA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Suresh',
            'last_name' => 'Raina',
            'phone' => '+91 9876543210',
            'trade_designation' => 'Grader Operator',
            'assigned_site_id' => $siteA->id,
            'daily_rate' => 950.00,
        ]);

        $createRes->assertRedirect('/workforce');
        $worker = Worker::where('first_name', 'Suresh')->first();
        $this->assertNotNull($worker);
        $this->assertEquals('Grader Operator', $worker->trade_designation);
        $this->assertEquals('active', $worker->status);

        // 2. Edit View Loads
        $editView = $this->actingAs($this->userA)->get("/workforce/{$worker->id}/edit");
        $editView->assertStatus(200);
        $editView->assertSee('Edit Worker Profile');
        $editView->assertSee('Suresh');

        // 3. Update Worker
        $updateRes = $this->actingAs($this->userA)->put("/workforce/{$worker->id}", [
            'worker_type' => 'employee',
            'first_name' => 'Suresh',
            'last_name' => 'Raina',
            'phone' => '+91 9999988888',
            'trade_designation' => 'Senior Heavy Grader Specialist',
            'assigned_site_id' => $siteA->id,
            'daily_rate' => 1200.00,
            'status' => 'active',
        ]);

        $updateRes->assertRedirect('/workforce');
        $worker->refresh();
        $this->assertEquals('Senior Heavy Grader Specialist', $worker->trade_designation);
        $this->assertEquals(1200.00, (float) $worker->daily_rate);
        $this->assertEquals('+91 9999988888', $worker->phone);
    }

    public function test_cannot_edit_worker_from_another_company(): void
    {
        TenantContext::setCompanyId($this->companyB->id);
        $workerB = Worker::create([
            'company_id' => $this->companyB->id,
            'worker_type' => 'employee',
            'first_name' => 'Beta',
            'last_name' => 'Worker',
            'code' => 'W-B-99',
            'daily_rate' => 800,
        ]);

        // User A in Company A attempts to edit Worker B
        TenantContext::setCompanyId($this->companyA->id);
        $response = $this->actingAs($this->userA)->get("/workforce/{$workerB->id}/edit");
        $response->assertStatus(404);
    }

    public function test_goods_receipt_cannot_be_performed_on_already_received_po(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $wh = Warehouse::create([
            'company_id' => $this->companyA->id,
            'name' => 'Central Depot',
            'code' => 'CD-1',
            'location_type' => 'central_warehouse',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'company_id' => $this->companyA->id,
            'po_number' => 'PO-TEST-001',
            'supplier_name' => 'Steel Corp',
            'order_date' => '2026-10-28',
            'total_amount' => 50000.00,
            'status' => 'received', // Already fully completed
            'created_by' => $this->userA->id,
        ]);

        $service = new InventoryService();
        $this->expectException(InvalidArgumentException::class);
        $service->recordGoodsReceipt($po, $wh->id, [], $this->userA);
    }

    public function test_schema_model_compatibility_aliases(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $aliasProj = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alias Proj Base',
            'code' => 'APB-1',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $aliasProj->id,
            'name' => 'Alias Site',
            'code' => 'ALS-1',
            'supervisor_id' => $this->userA->id,
            'status' => 'active',
        ]);

        $worker = Worker::create([
            'company_id' => $this->companyA->id,
            'worker_type' => 'employee',
            'code' => 'W-ALS-01',
            'first_name' => 'Alias',
            'last_name' => 'Worker',
            'trade_designation' => 'Mechanic',
            'assigned_site_id' => $site->id,
            'daily_rate' => 850,
        ]);

        // Test Worker aliases
        $this->assertEquals('W-ALS-01', $worker->worker_code);
        $this->assertEquals('Mechanic', $worker->trade_skill);
        $this->assertEquals($site->id, $worker->default_site_id);

        // Test Site alias
        $this->assertEquals($this->userA->id, $site->site_manager_id);

        // Test Project alias
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Alias Project',
            'code' => 'AP-1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);
        $this->assertEquals('2026-12-31', $project->target_completion_date->format('Y-m-d'));

        // Test WorkOrder notes aliases
        $wo = WorkOrder::create([
            'company_id' => $this->companyA->id,
            'project_id' => $project->id,
            'order_number' => 'WO-ALS-01',
            'title' => 'Alias WO',
            'status' => 'submitted',
            'created_by' => $this->userA->id,
            'site_manager_notes' => 'Site Note Example',
            'senior_manager_notes' => 'Senior Note Example',
        ]);
        $this->assertEquals('Site Note Example', $wo->site_notes);
        $this->assertEquals('Senior Note Example', $wo->senior_notes);
    }
}
