<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UiUxSimplificationAndLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected User $supervisor;
    protected User $siteManager;
    protected User $seniorManager;
    protected User $warehouseManager;
    protected Site $site;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'NSD Infrastructure Ltd', 'code' => 'NSD', 'is_active' => true]);
        TenantContext::setCompanyId($this->company->id);

        $roles = [
            'company_admin' => Role::create(['company_id' => $this->company->id, 'name' => 'Admin', 'slug' => 'company_admin', 'permissions' => ['*']]),
            'site_supervisor' => Role::create(['company_id' => $this->company->id, 'name' => 'Supervisor', 'slug' => 'site_supervisor', 'permissions' => ['attendance.muster', 'workforce.view', 'workforce.create']]),
            'site_manager' => Role::create(['company_id' => $this->company->id, 'name' => 'Site Manager', 'slug' => 'site_manager', 'permissions' => ['work_orders.view', 'work_orders.approve_site', 'procurement.requests.view', 'procurement.requests.approve']]),
            'senior_manager' => Role::create(['company_id' => $this->company->id, 'name' => 'Senior VP', 'slug' => 'senior_manager', 'permissions' => ['work_orders.view', 'work_orders.approve_senior', 'procurement.requests.view', 'procurement.requests.approve']]),
            'warehouse_manager' => Role::create(['company_id' => $this->company->id, 'name' => 'Depot Keeper', 'slug' => 'warehouse_manager', 'permissions' => ['procurement.orders.view', 'procurement.orders.receive', 'inventory.view']]),
        ];

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Expressway Pkg 3',
            'code' => 'EXP-3',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Bridge 104 Site',
            'code' => 'BR-104',
            'location' => 'Km 104',
            'status' => 'active',
        ]);

        $this->admin = $this->createUser('admin@nsd.local', $roles['company_admin']);
        $this->supervisor = $this->createUser('supervisor@nsd.local', $roles['site_supervisor']);
        $this->siteManager = $this->createUser('site.mgr@nsd.local', $roles['site_manager']);
        $this->seniorManager = $this->createUser('senior.vp@nsd.local', $roles['senior_manager']);
        $this->warehouseManager = $this->createUser('warehouse@nsd.local', $roles['warehouse_manager']);

        $this->site->assignedUsers()->attach($this->supervisor->id);
    }

    protected function createUser(string $email, Role $role): User
    {
        $user = User::create([
            'name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->companies()->attach($this->company->id, ['role_id' => $role->id, 'status' => 'active', 'is_primary' => true]);
        return $user;
    }

    public function test_multilingual_locale_switch_and_ui_rendering(): void
    {
        // 1. Switch to Hindi
        $switchHi = $this->actingAs($this->admin)->post('/locale', ['locale' => 'hi']);
        $switchHi->assertRedirect();
        $switchHi->assertSessionHas('locale', 'hi');

        // Check Hindi rendering on dashboard
        $hiDashboard = $this->actingAs($this->admin)
            ->withSession(['locale' => 'hi'])
            ->get('/dashboard');

        $hiDashboard->assertStatus(200);
        $hiDashboard->assertSee('संचालन डैशबोर्ड');
        $hiDashboard->assertSee('दैनिक विवरण');
        $hiDashboard->assertSee('कामगार');
        $hiDashboard->assertSee('मस्टर रोल भरें');

        // 2. Switch to Marathi
        $switchMr = $this->actingAs($this->admin)->post('/locale', ['locale' => 'mr']);
        $switchMr->assertRedirect();
        $switchMr->assertSessionHas('locale', 'mr');

        // Check Marathi rendering on dashboard
        $mrDashboard = $this->actingAs($this->admin)
            ->withSession(['locale' => 'mr'])
            ->get('/dashboard');

        $mrDashboard->assertStatus(200);
        $mrDashboard->assertSee('कामकाज डॅशबोर्ड');
        $mrDashboard->assertSee('दैनिक आढावा');
        $mrDashboard->assertSee('मस्टर रोल भरा');
        $mrDashboard->assertSee('सामग्री व साठा');
    }

    public function test_role_adaptive_dashboard_site_supervisor(): void
    {
        $response = $this->actingAs($this->supervisor)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee("Today's Work", false);
        $response->assertSee('Bridge 104 Site');
        $response->assertSee('Take Attendance');
    }

    public function test_role_adaptive_dashboard_site_manager_and_senior_manager(): void
    {
        // Create a submitted work order
        WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'order_number' => 'WO-TEST-99',
            'title' => 'Culvert Casting',
            'status' => 'submitted',
            'created_by' => $this->supervisor->id,
        ]);

        $smResponse = $this->actingAs($this->siteManager)->get('/dashboard');
        $smResponse->assertStatus(200);
        $smResponse->assertSee('Action Required');
        $smResponse->assertSee('Awaiting Site Manager (Tier 1)');

        $vpResponse = $this->actingAs($this->seniorManager)->get('/dashboard');
        $vpResponse->assertStatus(200);
        $vpResponse->assertSee('Action Required');
        $vpResponse->assertSee('Awaiting Senior VP (Tier 2)');
    }

    public function test_role_adaptive_dashboard_warehouse_depot_keeper(): void
    {
        $response = $this->actingAs($this->warehouseManager)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Materials &amp; Deliveries', false);
        $response->assertSee('Central Depot Keeper');
        $response->assertSee('Receive Goods');
    }

    public function test_operational_screens_contain_mobile_responsive_views(): void
    {
        // 1. Workforce directory
        $wfResponse = $this->actingAs($this->admin)->get('/workforce');
        $wfResponse->assertStatus(200);
        $wfResponse->assertSee('hidden sm:block', false);
        $wfResponse->assertSee('sm:hidden', false);

        // 2. Work Orders
        $woResponse = $this->actingAs($this->admin)->get('/work-orders');
        $woResponse->assertStatus(200);
        $woResponse->assertSee('hidden sm:block', false);
        $woResponse->assertSee('sm:hidden', false);

        // 3. Purchase Orders
        $poResponse = $this->actingAs($this->admin)->get('/procurement/orders');
        $poResponse->assertStatus(200);
        $poResponse->assertSee('hidden sm:block', false);
        $poResponse->assertSee('sm:hidden', false);

        // 4. Inventory Stocks
        $stockResponse = $this->actingAs($this->admin)->get('/inventory/stocks');
        $stockResponse->assertStatus(200);
        $stockResponse->assertSee('hidden sm:block', false);
        $stockResponse->assertSee('sm:hidden', false);
    }
}
