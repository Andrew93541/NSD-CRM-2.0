<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Operations\WorkOrderService;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class WorkOrderTwoTierApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected Site $site;
    protected User $siteManager;
    protected User $seniorManager;
    protected User $regularStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'NSD Co', 'code' => 'NSD', 'is_active' => true]);
        TenantContext::setCompanyId($this->company->id);

        $roleSiteMgr = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['work_orders.create', 'work_orders.approve_site', 'work_orders.view'],
        ]);

        $roleSeniorMgr = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => ['work_orders.approve_senior', 'work_orders.view'],
        ]);

        $roleStaff = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Staff',
            'slug' => 'office_staff',
            'permissions' => ['work_orders.view'],
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Highway NH-48',
            'code' => 'NH-48',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Sector 1',
            'code' => 'S1',
            'status' => 'active',
        ]);

        $this->siteManager = User::create([
            'name' => 'Arun Site Manager',
            'email' => 'arun@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->siteManager->companies()->attach($this->company->id, ['role_id' => $roleSiteMgr->id]);
        $this->site->assignedUsers()->attach($this->siteManager->id);

        $this->seniorManager = User::create([
            'name' => 'Vikram Senior VP',
            'email' => 'vikram@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->seniorManager->companies()->attach($this->company->id, ['role_id' => $roleSeniorMgr->id]);

        $this->regularStaff = User::create([
            'name' => 'Pooja Staff',
            'email' => 'pooja@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->regularStaff->companies()->attach($this->company->id, ['role_id' => $roleStaff->id]);
    }

    public function test_complete_two_tier_approval_lifecycle(): void
    {
        $service = new WorkOrderService();

        // 1. Create Work Order
        $wo = $service->createWorkOrder([
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'order_type' => 'client_issued',
            'client_reference' => 'NHAI-902',
            'title' => 'Bridge Pier Foundation',
            'estimated_amount' => 500000.00,
        ], $this->siteManager);

        $this->assertEquals('submitted', $wo->status);
        $this->assertNull($wo->site_manager_approved_at);
        $this->assertNull($wo->senior_manager_approved_at);

        // 2. Tier 1: Site Manager Approval
        $service->approveBySiteManager($wo, $this->siteManager, 'Verified on ground');
        $wo->refresh();
        $this->assertEquals('site_manager_approved', $wo->status);
        $this->assertNotNull($wo->site_manager_approved_at);
        $this->assertEquals($this->siteManager->id, $wo->site_manager_id);

        // 3. Tier 2: Senior Manager Approval
        $service->approveBySeniorManager($wo, $this->seniorManager, 'Authorized for contract execution');
        $wo->refresh();
        $this->assertEquals('senior_manager_approved', $wo->status);
        $this->assertNotNull($wo->senior_manager_approved_at);
        $this->assertEquals($this->seniorManager->id, $wo->senior_manager_id);

        // Check audit trail
        $this->assertCount(2, $wo->approvals);
        $this->assertEquals('site_manager', $wo->approvals[0]->stage);
        $this->assertEquals('senior_manager', $wo->approvals[1]->stage);
    }

    public function test_senior_manager_cannot_approve_before_site_manager(): void
    {
        $service = new WorkOrderService();

        $wo = $service->createWorkOrder([
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'order_type' => 'internal',
            'title' => 'Haul Road Maintenance',
        ], $this->siteManager);

        $this->expectException(InvalidArgumentException::class);
        $service->approveBySeniorManager($wo, $this->seniorManager, 'Trying to skip Tier 1');
    }

    public function test_unauthorized_staff_cannot_approve_work_order(): void
    {
        $wo = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'order_number' => 'WO-TEST-001',
            'title' => 'Unauthorized test',
            'status' => 'submitted',
            'created_by' => $this->siteManager->id,
        ]);

        $response = $this->actingAs($this->regularStaff)->post("/work-orders/{$wo->id}/approve-site", [
            'notes' => 'Attempting unauthorized approval',
        ]);

        $response->assertStatus(403);
    }

    public function test_rejection_halts_work_order_and_records_mandatory_reason(): void
    {
        $service = new WorkOrderService();

        $wo = $service->createWorkOrder([
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'title' => 'Flawed Work Order',
        ], $this->siteManager);

        $service->rejectWorkOrder($wo, $this->siteManager, 'Incomplete engineering drawings and budget mismatch.');

        $wo->refresh();
        $this->assertEquals('rejected', $wo->status);
        $this->assertEquals('Incomplete engineering drawings and budget mismatch.', $wo->rejection_reason);
        $this->assertEquals($this->siteManager->id, $wo->rejected_by);
        $this->assertNotNull($wo->rejected_at);
    }

    public function test_site_a_manager_cannot_view_approve_or_reject_site_b_work_order(): void
    {
        $siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Sector 2 Unassigned',
            'code' => 'S2',
            'status' => 'active',
        ]);

        $woSiteB = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $siteB->id,
            'order_number' => 'WO-NSD-2026-099',
            'title' => 'Site B Exclusive Work Order',
            'status' => 'submitted',
            'created_by' => $this->seniorManager->id,
        ]);

        // Site A Manager is NOT assigned to Site B -> index hides Site B work order
        $indexResp = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/work-orders');
        $indexResp->assertStatus(200);
        $indexResp->assertDontSee('Site B Exclusive Work Order');

        // Direct show, approve-site, and reject on Site B work order must return 403
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->get("/work-orders/{$woSiteB->id}")
            ->assertStatus(403);

        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/work-orders/{$woSiteB->id}/approve-site", ['notes' => 'Cross-site attempt'])
            ->assertStatus(403);

        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/work-orders/{$woSiteB->id}/reject", ['rejection_reason' => 'Cross-site reject attempt'])
            ->assertStatus(403);
    }

    public function test_private_work_order_attachment_upload_download_delete_and_cross_site_cross_tenant_denial(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $file = \Illuminate\Http\UploadedFile::fake()->create('boq_contract.pdf', 256, 'application/pdf');

        $storeResp = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/work-orders', [
                'project_id' => $this->project->id,
                'site_id' => $this->site->id,
                'order_type' => 'client_issued',
                'title' => 'Retaining Wall Package',
                'estimated_amount' => 350000,
                'attachment' => $file,
            ]);

        $wo = WorkOrder::where('title', 'Retaining Wall Package')->firstOrFail();
        $storeResp->assertRedirect(route('work_orders.show', $wo));

        $att = $wo->attachments()->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($att->file_path);

        // Authorized site manager can download private attachment with nosniff header
        $dlResp = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.attachments.download', [$wo, $att]));
        $dlResp->assertStatus(200);
        $dlResp->assertHeader('X-Content-Type-Options', 'nosniff');

        // Unassigned site manager from Site B cannot download Site A's work order attachment (403)
        $roleSiteMgr = Role::where('company_id', $this->company->id)->where('slug', 'site_manager')->firstOrFail();
        $otherSiteMgr = User::create([
            'name' => 'Other Site Manager',
            'email' => 'other.sm@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $otherSiteMgr->companies()->attach($this->company->id, ['role_id' => $roleSiteMgr->id]);

        $this->actingAs($otherSiteMgr)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.attachments.download', [$wo, $att]))
            ->assertStatus(403);

        // Cross-tenant admin gets 404
        $companyB = Company::create(['name' => 'Other Co', 'code' => 'OTH', 'is_active' => true]);
        $roleAdminB = Role::create(['company_id' => $companyB->id, 'name' => 'Admin B', 'slug' => 'company_admin', 'permissions' => ['*']]);
        $adminB = User::create(['name' => 'Admin B', 'email' => 'admin@oth.local', 'password' => Hash::make('password'), 'is_active' => true]);
        $adminB->companies()->attach($companyB->id, ['role_id' => $roleAdminB->id]);

        $this->actingAs($adminB)
            ->withSession(['active_company_id' => $companyB->id])
            ->get("/work-orders/{$wo->id}/attachments/{$att->id}/download")
            ->assertStatus(404);

        // Authorized site manager can delete attachment
        $delResp = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->delete(route('work_orders.attachments.destroy', [$wo, $att]));
        $delResp->assertRedirect();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($att->file_path);
    }

    public function test_warehouse_manager_cannot_access_workforce_or_work_orders_and_locked_sequence_prevents_collision(): void
    {
        $roleWh = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Warehouse Manager',
            'slug' => 'warehouse_manager',
            'permissions' => ['inventory.view', 'inventory.manage'],
        ]);
        $whUser = User::create([
            'name' => 'Depot Keeper',
            'email' => 'wh@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $whUser->companies()->attach($this->company->id, ['role_id' => $roleWh->id]);

        $this->actingAs($whUser)->withSession(['active_company_id' => $this->company->id])->get('/workforce')->assertStatus(403);
        $this->actingAs($whUser)->withSession(['active_company_id' => $this->company->id])->get('/attendance/muster')->assertStatus(403);
        $this->actingAs($whUser)->withSession(['active_company_id' => $this->company->id])->get('/work-orders')->assertStatus(403);

        // Verify locked sequence never collides even when a prior work order is deleted
        $service = new WorkOrderService();
        $wo1 = $service->createWorkOrder(['project_id' => $this->project->id, 'site_id' => $this->site->id, 'title' => 'Order 1'], $this->siteManager);
        $wo2 = $service->createWorkOrder(['project_id' => $this->project->id, 'site_id' => $this->site->id, 'title' => 'Order 2'], $this->siteManager);
        $wo1->delete();
        $wo3 = $service->createWorkOrder(['project_id' => $this->project->id, 'site_id' => $this->site->id, 'title' => 'Order 3'], $this->siteManager);

        $this->assertNotEquals($wo2->order_number, $wo3->order_number);
        $this->assertStringEndsWith('-003', $wo3->order_number);
    }
}
