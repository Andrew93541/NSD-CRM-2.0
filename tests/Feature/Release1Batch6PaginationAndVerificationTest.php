<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteNote;
use App\Models\Task;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Release1Batch6PaginationAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Project $project;

    private Site $siteA;

    private Site $siteB;

    private User $admin;

    private User $siteManagerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Deccan Infra Corridors Ltd',
            'code' => 'DICL',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        TenantContext::setCompanyId($this->company->id);

        $defaults = PermissionCatalog::defaultRolePermissions();
        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => $defaults['company_admin'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $smRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => $defaults['site_manager'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-DIC-01',
            'name' => 'Expressway Package 7',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-DIC-A',
            'name' => 'Toll Plaza Chainage 12',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-DIC-B',
            'name' => 'Interchange Chainage 48',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'DICL Admin',
            'email' => 'admin@dicl.test',
            'password' => Hash::make('StrongPass#2026'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => false,
        ]);
        $this->admin->companies()->attach($this->company->id, [
            'role_id' => $adminRole->id,
            'is_primary' => true,
            'status' => 'active',
            'restrict_to_assigned_sites' => false,
        ]);

        $this->siteManagerA = User::create([
            'name' => 'Site Manager A',
            'email' => 'sma@dicl.test',
            'password' => Hash::make('StrongPass#2026'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => true,
        ]);
        $this->siteManagerA->companies()->attach($this->company->id, [
            'role_id' => $smRole->id,
            'is_primary' => true,
            'status' => 'active',
            'restrict_to_assigned_sites' => true,
        ]);
        $this->siteManagerA->sites()->sync([$this->siteA->id]);
    }

    public function test_monthly_attendance_register_paginates_workers_and_preserves_filters_and_site_boundaries(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            Worker::create([
                'company_id' => $this->company->id,
                'worker_type' => 'employee',
                'code' => sprintf('EMP-DIC-%03d', $i),
                'first_name' => sprintf('WorkerA%02d', $i),
                'last_name' => 'Patil',
                'trade_designation' => 'Operator',
                'assigned_site_id' => $this->siteA->id,
                'pay_type' => 'daily',
                'daily_rate' => 900,
                'status' => 'active',
                'joining_date' => '2026-01-01',
            ]);
        }

        Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-DIC-B01',
            'first_name' => 'SiteBOnly',
            'last_name' => 'Worker',
            'trade_designation' => 'Operator',
            'assigned_site_id' => $this->siteB->id,
            'pay_type' => 'daily',
            'daily_rate' => 900,
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ]);

        $page1 = $this->actingAs($this->siteManagerA)->get(route('attendance.register', [
            'mode' => 'month',
            'month' => '2026-10',
            'per_page' => 5,
            'page' => 1,
        ]));
        $page1->assertOk()
            ->assertSee('WorkerA01')
            ->assertSee('WorkerA05')
            ->assertDontSee('WorkerA06')
            ->assertDontSee('SiteBOnly');

        $matrix1 = $page1->viewData('monthMatrix');
        $this->assertSame(8, $matrix1['summary']['total_workers']);
        $this->assertCount(5, $matrix1['rows']);

        $page2 = $this->actingAs($this->siteManagerA)->get(route('attendance.register', [
            'mode' => 'month',
            'month' => '2026-10',
            'per_page' => 5,
            'page' => 2,
        ]));
        $page2->assertOk()
            ->assertSee('WorkerA06')
            ->assertSee('WorkerA08')
            ->assertDontSee('WorkerA01');

        $matrix2 = $page2->viewData('monthMatrix');
        $this->assertSame(8, $matrix2['summary']['total_workers']);
        $this->assertCount(3, $matrix2['rows']);

        // Unassigned site_id filter blocked with 403
        $this->actingAs($this->siteManagerA)->get(route('attendance.register', [
            'mode' => 'month',
            'site_id' => $this->siteB->id,
        ]))->assertForbidden();
    }

    public function test_tasks_and_site_notes_paginate_cleanly_with_query_strings_and_site_isolation(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            Task::create([
                'company_id' => $this->company->id,
                'project_id' => $this->project->id,
                'site_id' => $this->siteA->id,
                'code' => sprintf('TSK-DIC-%03d', $i),
                'title' => sprintf('Culvert Formwork Batch %02d', $i),
                'priority' => 'high',
                'status' => 'todo',
                'created_by' => $this->admin->id,
            ]);

            SiteNote::create([
                'company_id' => $this->company->id,
                'project_id' => $this->project->id,
                'site_id' => $this->siteA->id,
                'user_id' => $this->siteManagerA->id,
                'category' => 'progress',
                'title' => sprintf('Progress Log Entry %02d', $i),
                'body' => 'Pour completed as scheduled.',
            ]);
        }

        Task::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteB->id,
            'code' => 'TSK-SITEB-99',
            'title' => 'Confidential Site B Task',
            'priority' => 'normal',
            'status' => 'todo',
            'created_by' => $this->admin->id,
        ]);

        $resp1 = $this->actingAs($this->siteManagerA)->get(route('tasks.index', [
            'view' => 'list',
            'per_page' => 5,
            'notes_per_page' => 5,
            'page' => 1,
        ]));
        $resp1->assertOk()->assertDontSee('Confidential Site B Task');
        $this->assertCount(5, $resp1->viewData('tasks'));
        $this->assertCount(5, $resp1->viewData('siteNotes'));
        $this->assertSame(7, $resp1->viewData('kpis')['total_active']);

        $resp2 = $this->actingAs($this->siteManagerA)->get(route('tasks.index', [
            'view' => 'list',
            'per_page' => 5,
            'notes_per_page' => 5,
            'page' => 2,
            'notes_page' => 2,
        ]));
        $resp2->assertOk();
        $this->assertCount(2, $resp2->viewData('tasks'));
        $this->assertCount(2, $resp2->viewData('siteNotes'));
    }

    public function test_reports_and_audit_logs_paginate_and_stream_sanitized_csv_exports(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            WorkOrder::create([
                'company_id' => $this->company->id,
                'project_id' => $this->project->id,
                'site_id' => $this->siteA->id,
                'order_number' => sprintf('WO-DIC-2026-%03d', $i),
                'title' => sprintf('Earthwork Package %02d', $i),
                'order_type' => 'client_issued',
                'status' => 'approved',
                'estimated_amount' => 100000,
                'created_by' => $this->admin->id,
            ]);
        }

        $woReport = $this->actingAs($this->siteManagerA)->get(route('reports.work_orders', ['per_page' => 5, 'page' => 2]));
        $woReport->assertOk();
        $this->assertSame(7, $woReport->viewData('totalOrders'));
        $this->assertCount(2, $woReport->viewData('orders'));

        // Inventory valuation report keeps full totalValuation across all pages while paginating rows
        $wh = Warehouse::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'name' => 'Site A Yard',
            'code' => 'WH-A1',
            'is_active' => true,
        ]);
        $cat = MaterialCategory::create(['company_id' => $this->company->id, 'name' => 'Fuels', 'code' => 'CAT-FUEL']);
        for ($i = 1; $i <= 6; $i++) {
            $mat = Material::create([
                'company_id' => $this->company->id,
                'category_id' => $cat->id,
                'name' => "Material Item {$i}",
                'code' => sprintf('MAT-IT-%02d', $i),
                'unit_of_measure' => 'Ltr',
                'standard_unit_cost' => 100.00,
                'is_active' => true,
            ]);
            InventoryStock::create([
                'company_id' => $this->company->id,
                'warehouse_id' => $wh->id,
                'material_id' => $mat->id,
                'quantity' => 10,
            ]);
        }

        $invReport = $this->actingAs($this->siteManagerA)->get(route('reports.inventory', ['per_page' => 5, 'page' => 1]));
        $invReport->assertOk();
        $this->assertEquals(6000.00, (float) $invReport->viewData('totalValuation'));
        $this->assertCount(5, $invReport->viewData('stocks'));

        // Audit logs pagination + streaming CSV formula sanitization
        for ($i = 1; $i <= 7; $i++) {
            AuditLog::create([
                'company_id' => $this->company->id,
                'user_id' => $this->admin->id,
                'site_id' => $this->siteA->id,
                'action' => $i === 1 ? '=HYPERLINK("http://evil.test")' : 'test.audit.entry',
                'description' => "Entry {$i}",
            ]);
        }

        $auditPage2 = $this->actingAs($this->admin)->get(route('reports.audit_logs', ['per_page' => 5, 'page' => 2]));
        $auditPage2->assertOk();
        $this->assertCount(2, $auditPage2->viewData('logs'));

        $csvResp = $this->actingAs($this->admin)->get(route('reports.audit_logs.export'));
        $csvResp->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $csvResp->streamedContent());

        // Restricted Site Manager A blocked from exporting Site B audit logs
        $this->actingAs($this->siteManagerA)
            ->get(route('reports.audit_logs', ['site_id' => $this->siteB->id]))
            ->assertForbidden();
    }
}
