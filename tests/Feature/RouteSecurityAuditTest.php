<?php

namespace Tests\Feature;

use App\Models\Company;
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

class RouteSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminA;
    protected User $staffA;
    protected Role $adminRole;
    protected Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create(['name' => 'Company A', 'code' => 'CMPA', 'is_active' => true]);
        $this->companyB = Company::create(['name' => 'Company B', 'code' => 'CMPB', 'is_active' => true]);

        $this->adminRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->staffRole = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Staff',
            'slug' => 'office_staff',
            'permissions' => ['attendance.view'],
        ]);

        $this->adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin.a@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->adminA->companies()->attach($this->companyA->id, ['role_id' => $this->adminRole->id, 'status' => 'active', 'is_primary' => true]);

        $this->staffA = User::create([
            'name' => 'Staff A',
            'email' => 'staff.a@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->staffA->companies()->attach($this->companyA->id, ['role_id' => $this->staffRole->id, 'status' => 'active', 'is_primary' => true]);
    }

    public function test_all_operational_routes_redirect_guests_to_login(): void
    {
        $guestRoutes = [
            '/',
            '/dashboard',
            '/sites',
            '/sites/create',
            '/workforce',
            '/workforce/create',
            '/workforce/subcontractors',
            '/attendance/muster',
            '/attendance/conflicts',
            '/attendance/corrections',
            '/work-orders',
            '/work-orders/create',
            '/procurement/requests',
            '/procurement/requests/create',
            '/procurement/orders',
            '/inventory/stocks',
            '/inventory/transactions',
            '/reports/workforce',
            '/reports/work-orders',
            '/reports/inventory',
        ];

        foreach ($guestRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_non_existent_record_ids_return_404(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $this->actingAs($this->adminA)->get('/sites/99999')->assertStatus(404);
        $this->actingAs($this->adminA)->get('/sites/99999/edit')->assertStatus(404);
        $this->actingAs($this->adminA)->get('/work-orders/99999')->assertStatus(404);
        $this->actingAs($this->adminA)->get('/workforce/99999')->assertStatus(404);
        $this->actingAs($this->adminA)->get('/workforce/99999/edit')->assertStatus(404);
        $this->actingAs($this->adminA)->get('/procurement/requests/99999/create-po')->assertStatus(404);
        $this->actingAs($this->adminA)->get('/procurement/orders/99999/receive')->assertStatus(404);
    }

    public function test_cross_company_records_return_404_due_to_tenant_scoping(): void
    {
        // Create records in Company B
        TenantContext::setCompanyId($this->companyB->id);

        $projB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Project B',
            'code' => 'PB-1',
            'status' => 'active',
        ]);

        $woB = WorkOrder::create([
            'company_id' => $this->companyB->id,
            'project_id' => $projB->id,
            'order_number' => 'WO-B-999',
            'title' => 'Secret Company B Order',
            'status' => 'submitted',
            'created_by' => $this->adminA->id,
        ]);

        $workerB = Worker::create([
            'company_id' => $this->companyB->id,
            'worker_type' => 'employee',
            'code' => 'W-B-999',
            'first_name' => 'Secret',
            'last_name' => 'Worker',
            'trade_designation' => 'Driver',
            'daily_rate' => 800,
        ]);

        $prB = PurchaseRequest::create([
            'company_id' => $this->companyB->id,
            'request_number' => 'PR-B-999',
            'requested_by' => $this->adminA->id,
            'status' => 'approved',
        ]);

        $poB = PurchaseOrder::create([
            'company_id' => $this->companyB->id,
            'purchase_request_id' => $prB->id,
            'po_number' => 'PO-B-999',
            'supplier_name' => 'Vendor B',
            'order_date' => '2026-10-28',
            'total_amount' => 10000,
            'status' => 'issued',
            'created_by' => $this->adminA->id,
        ]);

        // Now operate as Admin of Company A
        TenantContext::setCompanyId($this->companyA->id);

        // All Company B record URLs must return 404
        $this->actingAs($this->adminA)->get("/work-orders/{$woB->id}")->assertStatus(404);
        $this->actingAs($this->adminA)->get("/workforce/{$workerB->id}/edit")->assertStatus(404);
        $this->actingAs($this->adminA)->get("/procurement/requests/{$prB->id}/create-po")->assertStatus(404);
        $this->actingAs($this->adminA)->get("/procurement/orders/{$poB->id}/receive")->assertStatus(404);

        // Actions targeting Company B records must also fail (404)
        $this->actingAs($this->adminA)->post("/work-orders/{$woB->id}/approve-site")->assertStatus(404);
        $this->actingAs($this->adminA)->post("/work-orders/{$woB->id}/approve-senior")->assertStatus(404);
        $this->actingAs($this->adminA)->post("/procurement/requests/{$prB->id}/approve")->assertStatus(404);
    }

    public function test_staff_cannot_perform_privileged_administrative_actions(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $projA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project A',
            'code' => 'PA-1',
            'status' => 'active',
        ]);

        $siteA = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projA->id,
            'name' => 'Site A',
            'code' => 'SA-1',
            'status' => 'active',
        ]);

        $wo = WorkOrder::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projA->id,
            'site_id' => $siteA->id,
            'order_number' => 'WO-A-001',
            'title' => 'Test Order',
            'status' => 'submitted',
            'created_by' => $this->adminA->id,
        ]);

        // Staff without approval permission cannot approve site
        $this->actingAs($this->staffA)->post("/work-orders/{$wo->id}/approve-site", [
            'notes' => 'Unauthorized approval attempt',
        ])->assertStatus(403);

        // Staff without approval permission cannot approve senior
        $this->actingAs($this->staffA)->post("/work-orders/{$wo->id}/approve-senior", [
            'notes' => 'Unauthorized senior approval',
        ])->assertStatus(403);

        // Staff cannot access work order create page
        $this->actingAs($this->staffA)->get('/work-orders/create')->assertStatus(403);
    }
}
