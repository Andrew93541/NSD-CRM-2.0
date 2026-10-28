<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ControllerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected User $siteManager;
    protected Site $site;
    protected Project $project;
    protected Worker $worker;
    protected Warehouse $warehouse;
    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'NSD Infrastructure Ltd', 'code' => 'NSD', 'is_active' => true]);
        TenantContext::setCompanyId($this->company->id);

        $roleAdmin = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $roleSiteMgr = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => [
                'work_orders.create',
                'work_orders.approve_site',
                'work_orders.view',
                'attendance.record',
                'attendance.view',
                'procurement.request',
            ],
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->companies()->attach($this->company->id, ['role_id' => $roleAdmin->id]);

        $this->siteManager = User::create([
            'name' => 'Site Manager User',
            'email' => 'site.mgr@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->siteManager->companies()->attach($this->company->id, ['role_id' => $roleSiteMgr->id]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Metro Package 3',
            'code' => 'MTR-3',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Station 4 Yard',
            'code' => 'SITE-M4',
            'status' => 'active',
        ]);
        $this->site->assignedUsers()->attach($this->siteManager->id);

        $this->worker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'first_name' => 'Sunil',
            'last_name' => 'Gawande',
            'code' => 'W-EMP-099',
            'trade_designation' => 'Grader Pilot',
            'assigned_site_id' => $this->site->id,
            'daily_rate' => 1100,
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Central Depot',
            'code' => 'WH-DEPOT',
            'location_type' => 'central_warehouse',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Aggregates',
            'code' => 'AGG',
        ]);

        $this->material = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'Crushed Rock 40mm',
            'code' => 'AGG-40',
            'unit_of_measure' => 'MT',
            'minimum_stock_level' => 100,
            'standard_unit_cost' => 600,
            'is_active' => true,
        ]);
    }

    public function test_dashboard_renders_successfully_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Operations Dashboard');
        $response->assertSee('NSD Infrastructure Ltd');
    }

    public function test_workforce_directory_and_worker_creation(): void
    {
        $response = $this->actingAs($this->admin)->get('/workforce');
        $response->assertStatus(200);
        $response->assertSee('Sunil Gawande');

        $createResponse = $this->actingAs($this->admin)->post('/workforce', [
            'worker_type' => 'direct_labour',
            'first_name' => 'Kiran',
            'last_name' => 'Bhoir',
            'trade_designation' => 'Mason Helper',
            'assigned_site_id' => $this->site->id,
            'daily_rate' => 650,
        ]);

        $createResponse->assertRedirect('/workforce');
        $this->assertDatabaseHas('workers', [
            'first_name' => 'Kiran',
            'last_name' => 'Bhoir',
            'worker_type' => 'direct_labour',
        ]);
    }

    public function test_daily_muster_roll_flow(): void
    {
        $musterView = $this->actingAs($this->siteManager)->get('/attendance/muster?site_id=' . $this->site->id);
        $musterView->assertStatus(200);
        $musterView->assertSee('Sunil Gawande');

        $submitMuster = $this->actingAs($this->siteManager)->post('/attendance/muster', [
            'site_id' => $this->site->id,
            'attendance_date' => '2026-10-28',
            'workers' => [
                $this->worker->id => [
                    [
                        'status' => 'present',
                        'clock_in' => '08:00',
                        'clock_out' => '17:00',
                    ],
                ],
            ],
        ]);

        $submitMuster->assertRedirect();
        $this->assertDatabaseHas('daily_attendances', [
            'worker_id' => $this->worker->id,
            'site_id' => $this->site->id,
            'status' => 'present',
            'worked_hours' => 9.00,
        ]);
    }

    public function test_work_order_creation_and_approval_flow(): void
    {
        $response = $this->actingAs($this->siteManager)->post('/work-orders', [
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'order_type' => 'client_issued',
            'client_reference' => 'MRDC-WO-099',
            'title' => 'Pier Foundation Work',
            'estimated_amount' => 150000,
        ]);

        $response->assertRedirect();
        $wo = WorkOrder::where('title', 'Pier Foundation Work')->first();
        $this->assertNotNull($wo);
        $this->assertEquals('submitted', $wo->status);

        // Site Manager Approval
        $siteApprove = $this->actingAs($this->siteManager)->post("/work-orders/{$wo->id}/approve-site", [
            'notes' => 'Verified on site',
        ]);
        $siteApprove->assertRedirect();
        $wo->refresh();
        $this->assertEquals('site_manager_approved', $wo->status);

        // Senior Manager (or Admin) Approval
        $seniorApprove = $this->actingAs($this->admin)->post("/work-orders/{$wo->id}/approve-senior", [
            'notes' => 'Executive approval granted',
        ]);
        $seniorApprove->assertRedirect();
        $wo->refresh();
        $this->assertEquals('senior_manager_approved', $wo->status);
    }

    public function test_reports_views_render_without_errors(): void
    {
        $wfReport = $this->actingAs($this->admin)->get('/reports/workforce');
        $wfReport->assertStatus(200);
        $wfReport->assertSee('Workforce Attendance');

        $woReport = $this->actingAs($this->admin)->get('/reports/work-orders');
        $woReport->assertStatus(200);
        $woReport->assertSee('Work Orders Execution Funnel');

        $invReport = $this->actingAs($this->admin)->get('/reports/inventory');
        $invReport->assertStatus(200);
        $invReport->assertSee('Material Valuation');
    }
}
