<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MeasurementEntry;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteNote;
use App\Models\Task;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3EndToEndWorkflowUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'code' => 'NSD-E2E',
            'name' => 'NSD Infrastructure E2E Corp',
            'legal_name' => 'NSD Infrastructure Pvt Ltd',
            'primary_location' => 'Pune, Maharashtra',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        TenantContext::setCompanyId($this->company->id);

        foreach (PermissionCatalog::defaultRolePermissions() as $slug => $perms) {
            Role::create([
                'company_id' => $this->company->id,
                'name' => $slug,
                'slug' => $slug,
                'description' => ucfirst(str_replace('_', ' ', $slug)),
                'permissions' => $perms,
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $this->companyAdmin = $this->createUserWithRole('Admin User', 'admin@nsd-e2e.test', 'company_admin');
    }

    private function createUserWithRole(
        string $name,
        string $email,
        string $roleName,
        array $sites = [],
        bool $restrictSites = false,
        ?array $overrides = null
    ): User {
        $role = Role::where('company_id', $this->company->id)
            ->where('slug', $roleName)
            ->firstOrFail();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass#2026',
            'is_active' => true,
            'must_change_password' => false,
            'current_company_id' => $this->company->id,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
            'restrict_to_assigned_sites' => $restrictSites,
            'permission_overrides' => $overrides ? json_encode($overrides) : null,
        ]);

        if (! empty($sites)) {
            $syncData = [];
            foreach ($sites as $siteId) {
                $syncData[$siteId] = [
                    'role_in_site' => $roleName,
                ];
            }
            $user->sites()->sync($syncData);
        }

        return $user;
    }

    public function test_workflow_1_and_2_company_admin_creates_project_sites_and_employee_logins(): void
    {
        // Workflow 2 Step 1: Open Create Project page and verify pre-suggested code PRJ-001
        $createProjectView = $this->actingAs($this->companyAdmin)
            ->get(route('projects.create'));
        $createProjectView->assertOk();
        $createProjectView->assertSee('PRJ-001');

        // Create Project
        $storeProject = $this->actingAs($this->companyAdmin)
            ->post(route('projects.store'), [
                'code' => 'PRJ-001',
                'name' => 'JSW Dolvi Hot Strip Mill Civil Works',
                'client_name' => 'JSW Steel Limited',
                'status' => 'active',
                'start_date' => now()->toDateString(),
            ]);
        $project = Project::where('code', 'PRJ-001')->firstOrFail();
        $storeProject->assertRedirect(route('projects.show', $project));

        // Open Create Site from Project context (?project_id=...) and verify pre-filled project & suggested SITE-001 code
        $createSiteView = $this->actingAs($this->companyAdmin)
            ->get(route('sites.create', ['project_id' => $project->id]));
        $createSiteView->assertOk();
        $createSiteView->assertSee('SITE-001');
        $createSiteView->assertSee('Address, GPS Coordinates & Shift Timings');

        // Store Site A and Site B
        $storeSiteA = $this->actingAs($this->companyAdmin)
            ->post(route('sites.store'), [
                'project_id' => $project->id,
                'code' => 'SITE-001',
                'name' => 'Dolvi SMS-2 Foundation Yard',
                'status' => 'active',
                'location' => 'Gate 4, Dolvi Works',
            ]);

        $siteA = Site::where('code', 'SITE-001')->firstOrFail();
        $storeSiteA->assertRedirect(route('sites.show', $siteA));

        // Next suggested code should automatically advance to SITE-002
        $this->actingAs($this->companyAdmin)
            ->get(route('sites.create', ['project_id' => $project->id]))
            ->assertOk()
            ->assertSee('SITE-002');

        $storeSiteB = $this->actingAs($this->companyAdmin)
            ->post(route('sites.store'), [
                'project_id' => $project->id,
                'code' => 'SITE-002',
                'name' => 'Thane Creek Piling Corridor',
                'status' => 'active',
                'location' => 'Sector 7',
            ]);

        $siteB = Site::where('code', 'SITE-002')->firstOrFail();
        $storeSiteB->assertRedirect(route('sites.show', $siteB));

        // Workflow 1: Company Admin creates a Site Manager employee login assigned to Site A
        $siteManagerRole = Role::where('company_id', $this->company->id)
            ->where('slug', 'site_manager')
            ->firstOrFail();

        $storeEmployee = $this->actingAs($this->companyAdmin)
            ->post(route('admin.users.store'), [
                'name' => 'Rohan Deshmukh',
                'email' => 'rohan.manager@nsd-e2e.test',
                'phone' => '+91 9820011223',
                'role_id' => $siteManagerRole->id,
                'status' => 'active',
                'restrict_to_assigned_sites' => '1',
                'site_ids' => [$siteA->id],
                'provisioning_mode' => 'manual',
                'password' => 'ManagerPass#2026',
                'password_confirmation' => 'ManagerPass#2026',
                'must_change_password' => '0',
            ]);

        $newManager = User::where('email', 'rohan.manager@nsd-e2e.test')->firstOrFail();
        $storeEmployee->assertRedirect(route('admin.users.show', $newManager));
        $this->assertTrue($newManager->hasRole('site_manager'));
        $this->assertEquals([$siteA->id], $newManager->accessibleSiteIds());
    }

    public function test_workflow_3_site_manager_registers_worker_and_site_scope_is_enforced_in_directory(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-W3',
            'name' => 'Bridge Corridor',
            'status' => 'active',
        ]);

        $siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-A',
            'name' => 'Assigned Site Alpha',
            'status' => 'active',
        ]);

        $siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-B',
            'name' => 'Unassigned Site Beta',
            'status' => 'active',
        ]);

        $siteManager = $this->createUserWithRole(
            'Site Manager Alpha',
            'sm.alpha@nsd-e2e.test',
            'site_manager',
            [$siteA->id],
            true
        );

        // Create a worker on unassigned Site B via Company Admin
        $otherSiteWorker = Worker::create([
            'company_id' => $this->company->id,
            'code' => 'EMP-2026-0099',
            'first_name' => 'Vikram',
            'last_name' => 'UnassignedSite',
            'worker_type' => 'employee',
            'trade_designation' => 'Fitter',
            'pay_type' => 'daily',
            'daily_rate' => 800,
            'assigned_site_id' => $siteB->id,
            'status' => 'active',
        ]);

        // Site Manager opens Create Worker form from Site A context (?site_id=...)
        $createView = $this->actingAs($siteManager)
            ->get(route('workforce.create', ['site_id' => $siteA->id]));
        $createView->assertOk();
        $createView->assertSee('Assigned Site Alpha');
        $createView->assertDontSee('Unassigned Site Beta');

        // Site Manager registers a new worker for Site A
        $storeResponse = $this->actingAs($siteManager)
            ->post(route('workforce.store'), [
                'first_name' => 'Ganesh',
                'last_name' => 'Shinde',
                'worker_type' => 'employee',
                'trade_designation' => 'Excavator Operator',
                'skill_level' => 'skilled',
                'department' => 'Civil & Earthworks',
                'pay_type' => 'daily',
                'daily_rate' => 950,
                'status' => 'active',
                'assigned_site_id' => $siteA->id,
            ]);

        $workerA = Worker::where('first_name', 'Ganesh')->firstOrFail();
        $storeResponse->assertRedirect(route('workforce.index'));

        // Site Manager views Workforce Directory: sees Ganesh (Site A), does NOT see Vikram (Site B)
        $indexResponse = $this->actingAs($siteManager)->get(route('workforce.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Ganesh Shinde');
        $indexResponse->assertDontSee('Vikram UnassignedSite');

        // Direct URL access to Site B worker profile is forbidden (403)
        $this->actingAs($siteManager)
            ->get(route('workforce.show', $otherSiteWorker))
            ->assertForbidden();
    }

    public function test_workflow_4_supervisor_marks_attendance_requests_correction_and_authorized_manager_approves(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-ATT',
            'name' => 'Attendance Project',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-ATT',
            'name' => 'Attendance Site',
            'status' => 'active',
        ]);

        $worker = Worker::create([
            'company_id' => $this->company->id,
            'code' => 'EMP-2026-0001',
            'first_name' => 'Suresh',
            'last_name' => 'Kamble',
            'worker_type' => 'employee',
            'trade_designation' => 'Mason',
            'pay_type' => 'daily',
            'daily_rate' => 750,
            'assigned_site_id' => $site->id,
            'status' => 'active',
        ]);

        $supervisor = $this->createUserWithRole('Site Supervisor', 'sup@nsd-e2e.test', 'supervisor', [$site->id], true);
        $seniorManagerDefault = $this->createUserWithRole('Senior Manager Default', 'sm@nsd-e2e.test', 'senior_manager', [$site->id], false);
        $authorizedApprover = $this->createUserWithRole(
            'Authorized Attendance Approver',
            'approver@nsd-e2e.test',
            'senior_manager',
            [$site->id],
            false,
            ['attendance.approve' => 'allow']
        );

        $today = now()->toDateString();

        // 1. Company Admin cannot mark attendance by default
        $this->actingAs($this->companyAdmin)
            ->post(route('attendance.store_muster'), [
                'site_id' => $site->id,
                'attendance_date' => $today,
                'workers' => [
                    $worker->id => ['status' => 'present'],
                ],
            ])
            ->assertForbidden();

        // 2. Supervisor marks daily attendance
        $this->actingAs($supervisor)
            ->post(route('attendance.store_muster'), [
                'site_id' => $site->id,
                'attendance_date' => $today,
                'workers' => [
                    $worker->id => [
                        'status' => 'absent',
                        'clock_in' => '',
                        'clock_out' => '',
                    ],
                ],
            ])
            ->assertRedirect();

        $attendance = DailyAttendance::where('worker_id', $worker->id)
            ->whereDate('attendance_date', $today)
            ->firstOrFail();
        $this->assertEquals('absent', $attendance->status);

        // 3. Supervisor submits a correction request with too short reason -> fails validation
        $this->actingAs($supervisor)
            ->post(route('attendance.request_correction', $attendance), [
                'proposed_status' => 'present',
                'reason' => 'abc',
            ])
            ->assertSessionHasErrors('reason');

        // 4. Supervisor submits valid correction request
        $this->actingAs($supervisor)
            ->post(route('attendance.request_correction', $attendance), [
                'proposed_status' => 'present',
                'proposed_clock_in' => '09:00',
                'proposed_clock_out' => '18:00',
                'reason' => 'Worker was deployed at Gate 2 unloading rebar and missed morning muster call.',
            ])
            ->assertRedirect();

        $correction = AttendanceCorrection::where('daily_attendance_id', $attendance->id)->firstOrFail();
        $this->assertEquals('pending', $correction->status);
        $this->assertEquals('absent', $correction->previous_status);

        // 5. Company Admin & Default Senior Manager CANNOT approve attendance correction without explicit grant
        $this->actingAs($this->companyAdmin)
            ->post(route('attendance.review_correction', $correction), [
                'action' => 'approved',
                'review_notes' => 'Admin trying to approve',
            ])
            ->assertForbidden();

        $this->actingAs($seniorManagerDefault)
            ->post(route('attendance.review_correction', $correction), [
                'action' => 'approved',
                'review_notes' => 'Senior Manager without explicit override trying to approve',
            ])
            ->assertForbidden();

        // 6. Explicitly authorized manager approves the correction
        $this->actingAs($authorizedApprover)
            ->post(route('attendance.review_correction', $correction), [
                'action' => 'approved',
                'review_notes' => 'Verified with Gate 2 register.',
            ])
            ->assertRedirect();

        $correction->refresh();
        $attendance->refresh();

        $this->assertEquals('approved', $correction->status);
        $this->assertEquals('absent', $correction->previous_status);
        $this->assertEquals('present', $attendance->status);
    }

    public function test_workflow_5_and_8_work_order_creation_approvals_measurements_and_ra_bill_lifecycle(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-WO',
            'name' => 'JSW Blast Furnace Civil Works',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-WO',
            'name' => 'BF-3 Cast House',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'code' => 'JSW-DOLVI',
            'name' => 'JSW Steel Ltd',
            'gstin' => '27AAACJ4323N1Z5',
        ]);

        $siteManager = $this->createUserWithRole('Site Manager WO', 'sm.wo@nsd-e2e.test', 'site_manager', [$site->id], true);
        $seniorManager = $this->createUserWithRole('Senior Manager WO', 'sr.wo@nsd-e2e.test', 'senior_manager', [$site->id], false);

        // Workflow 5: Site Manager opens Create Work Order from Site context and submits BOQ
        $this->actingAs($siteManager)
            ->get(route('work_orders.create', ['site_id' => $site->id, 'project_id' => $project->id]))
            ->assertOk()
            ->assertSee('Contractor Details, GST Rates & Billing Terms');

        $storeWo = $this->actingAs($siteManager)
            ->post(route('work_orders.store'), [
                'order_type' => 'client_issued',
                'project_id' => $project->id,
                'site_id' => $site->id,
                'client_id' => $client->id,
                'po_number' => '4700998877',
                'po_date' => now()->toDateString(),
                'title' => 'Excavation & PCC for BF-3 Foundation',
                'cgst_rate' => 9,
                'sgst_rate' => 9,
                'retention_percent' => 5,
                'estimated_amount' => 100000,
                'items' => [
                    [
                        'sr_no' => '1.10',
                        'service_code' => '6003235',
                        'sac_code' => '995419',
                        'description' => 'Earthwork Excavation in Soft Rock',
                        'unit' => 'M3',
                        'ordered_quantity' => 200,
                        'rate' => 500,
                    ],
                ],
            ]);

        $workOrder = WorkOrder::where('po_number', '4700998877')->firstOrFail();
        $storeWo->assertRedirect(route('work_orders.show', $workOrder));
        $this->assertEquals('submitted', $workOrder->status);

        // Tier 1 Approval by Site Manager
        $this->actingAs($siteManager)
            ->post(route('work_orders.approve_site', $workOrder))
            ->assertRedirect();
        $workOrder->refresh();
        $this->assertEquals('site_manager_approved', $workOrder->status);

        // Tier 2 Approval by Senior Manager -> status becomes senior_manager_approved
        $this->actingAs($seniorManager)
            ->post(route('work_orders.approve_senior', $workOrder))
            ->assertRedirect();
        $workOrder->refresh();
        $this->assertEquals('senior_manager_approved', $workOrder->status);

        // Workflow 8: Record Measurement -> Generate RA Bill -> Submit -> Verify Tier 1 -> Approve Tier 2 -> Export XLSX
        $woItem = WorkOrderItem::where('work_order_id', $workOrder->id)->firstOrFail();

        $this->actingAs($siteManager)
            ->post(route('work_orders.measurements.store', $workOrder), [
                'work_order_item_id' => $woItem->id,
                'measured_on' => now()->toDateString(),
                'location_chainage' => 'Chainage 0+000 to 0+050 Pit 1',
                'nos' => 1,
                'length' => 10,
                'breadth' => 5,
                'depth_height' => 2,
            ])
            ->assertRedirect(route('work_orders.show', $workOrder));

        $measurement = MeasurementEntry::where('work_order_id', $workOrder->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $measurement->computed_quantity);

        // Generate RA Bill
        $this->actingAs($siteManager)
            ->post(route('work_orders.ra_bills.store', $workOrder), [
                'invoice_date' => now()->toDateString(),
                'period_from' => now()->startOfMonth()->toDateString(),
                'period_to' => now()->toDateString(),
                'advance_recovery' => 0,
                'other_deductions' => 0,
                'items' => [
                    $woItem->id => [
                        'claimed_quantity' => 100,
                        'hold_quantity' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $raBill = RaBill::where('work_order_id', $workOrder->id)->firstOrFail();
        $this->assertEquals('draft', $raBill->status);
        $this->assertEquals(50000.0, (float) $raBill->current_bill_amount);

        // Submit RA Bill for approval
        $this->actingAs($siteManager)
            ->post(route('work_orders.ra_bills.submit', [$workOrder, $raBill]))
            ->assertRedirect();
        $raBill->refresh();
        $this->assertEquals('submitted', $raBill->status);

        // Tier 1 Verify RA Bill (Site Manager)
        $this->actingAs($siteManager)
            ->post(route('work_orders.ra_bills.approve_site', [$workOrder, $raBill]))
            ->assertRedirect();
        $raBill->refresh();
        $this->assertEquals('site_manager_approved', $raBill->status);

        // Tier 2 Approve RA Bill (Senior Manager)
        $this->actingAs($seniorManager)
            ->post(route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill]))
            ->assertRedirect();
        $raBill->refresh();
        $this->assertEquals('approved', $raBill->status);

        // Export RA Bill Excel (.xlsx)
        $this->actingAs($this->companyAdmin)
            ->get(route('work_orders.ra_bills.export_xlsx', [$workOrder, $raBill]))
            ->assertOk();
    }

    public function test_workflow_6_supervisor_creates_tasks_and_site_notes_with_site_isolation(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-TSK',
            'name' => 'Task Project',
            'status' => 'active',
        ]);

        $siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-TA',
            'name' => 'Task Site Alpha',
            'status' => 'active',
        ]);

        $siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-TB',
            'name' => 'Task Site Beta',
            'status' => 'active',
        ]);

        $supervisorA = $this->createUserWithRole('Supervisor Alpha', 'sup.a@nsd-e2e.test', 'supervisor', [$siteA->id], true);

        // Create a task on Site B as Company Admin
        Task::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'site_id' => $siteB->id,
            'code' => 'TSK-BETA-99',
            'title' => 'Confidential Beta Site Task',
            'priority' => 'high',
            'status' => 'todo',
            'created_by' => $this->companyAdmin->id,
        ]);

        // Supervisor A creates a task on Site A
        $this->actingAs($supervisorA)
            ->post(route('tasks.store'), [
                'site_id' => $siteA->id,
                'title' => 'Complete Rebar Caging at Pier P4',
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertTrue(Task::where('title', 'Complete Rebar Caging at Pier P4')->exists());

        // Supervisor A posts a site note on Site A
        $this->actingAs($supervisorA)
            ->post(route('tasks.notes.store'), [
                'site_id' => $siteA->id,
                'category' => 'handover',
                'title' => 'Day Shift Concrete Pour Handover',
                'body' => 'Completed 45 M3 pour on Pier P4 base.',
            ])
            ->assertRedirect();

        $this->assertTrue(SiteNote::where('title', 'Day Shift Concrete Pour Handover')->exists());

        // Supervisor A views Tasks & Notes index -> sees Task A & Note A, does NOT see Beta Site Task
        $indexResponse = $this->actingAs($supervisorA)->get(route('tasks.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Complete Rebar Caging at Pier P4');
        $indexResponse->assertSee('Day Shift Concrete Pour Handover');
        $indexResponse->assertDontSee('Confidential Beta Site Task');
    }

    public function test_workflow_7_office_staff_creates_purchase_request_po_and_warehouse_grn(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-PROC',
            'name' => 'Procurement Project',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-PROC',
            'name' => 'Dolvi Site Store',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'site_id' => $site->id,
            'code' => 'WH-DOLVI-01',
            'name' => 'Dolvi Yard Warehouse',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Cement & Binders',
            'code' => 'CAT-CEM',
        ]);

        $material = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'code' => 'MAT-CEM-53',
            'name' => 'OPC 53 Grade Cement (50kg Bag)',
            'unit_of_measure' => 'BAG',
            'reorder_level' => 100,
        ]);

        $siteManager = $this->createUserWithRole('Site Manager Requester', 'sm.proc@nsd-e2e.test', 'site_manager', [$site->id], true);
        $seniorManager = $this->createUserWithRole('Senior Manager Approver', 'srmgr@nsd-e2e.test', 'senior_manager', [$site->id], false);

        // 1. Site Manager opens Create Purchase Request page and sees the Urgency selector
        $this->actingAs($siteManager)
            ->get(route('procurement.create_request', ['site_id' => $site->id]))
            ->assertOk()
            ->assertSee('Urgency / Priority');

        // 2. Site Manager submits Purchase Request (testing default urgency fallback when omitted)
        $this->actingAs($siteManager)
            ->post(route('procurement.store_request'), [
                'site_id' => $site->id,
                'warehouse_id' => $warehouse->id,
                'items' => [
                    [
                        'material_id' => $material->id,
                        'quantity' => 250,
                        'purpose_notes' => 'For Pier P4 foundation slab',
                    ],
                ],
            ])
            ->assertRedirect(route('procurement.requests'));

        $pr = PurchaseRequest::where('site_id', $site->id)->firstOrFail();
        $prItem = $pr->items()->firstOrFail();
        $this->assertEquals('pending', $pr->status);
        $this->assertEquals('normal', $pr->urgency);

        // 3. Senior Manager approves Purchase Request
        $this->actingAs($seniorManager)
            ->post(route('procurement.approve_request', $pr))
            ->assertRedirect();
        $pr->refresh();
        $this->assertEquals('approved', $pr->status);

        // 4. Senior Manager generates Purchase Order from approved PR
        $this->actingAs($seniorManager)
            ->post(route('procurement.store_po', $pr), [
                'supplier_name' => 'UltraTech Cement Ltd',
                'supplier_phone' => '9820198201',
                'expected_delivery_date' => now()->addDays(3)->toDateString(),
                'unit_prices' => [
                    $prItem->id => 360,
                ],
            ])
            ->assertRedirect(route('procurement.orders'));

        $po = PurchaseOrder::where('purchase_request_id', $pr->id)->firstOrFail();
        $poItem = $po->items()->firstOrFail();

        // 5. Record Goods Receipt (GRN) into Warehouse and verify inventory stock increases
        $this->actingAs($seniorManager)
            ->post(route('procurement.confirm_receipt', $po), [
                'destination_warehouse_id' => $warehouse->id,
                'challan_or_invoice_no' => 'DC-99412',
                'received' => [
                    $poItem->id => 250,
                ],
            ])
            ->assertRedirect(route('procurement.orders'));

        $this->assertDatabaseHas('inventory_stocks', [
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 250,
        ]);
    }

    public function test_workflow_9_management_reviews_reports_and_office_staff_cannot_export_attendance_by_default(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-REP',
            'name' => 'Reports Project',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-REP',
            'name' => 'Reports Site',
            'status' => 'active',
        ]);

        $seniorManager = $this->createUserWithRole('Senior Manager Reports', 'sm.rep@nsd-e2e.test', 'senior_manager', [$site->id], false);
        $officeStaff = $this->createUserWithRole('Office Staff No Export', 'office.noexp@nsd-e2e.test', 'office_staff', [$site->id], true);

        // Management can view all operational reports and monthly attendance register
        $this->actingAs($seniorManager)->get(route('reports.workforce'))->assertOk();
        $this->actingAs($seniorManager)->get(route('reports.multi_site'))->assertOk();
        $this->actingAs($seniorManager)->get(route('reports.work_orders'))->assertOk();
        $this->actingAs($seniorManager)->get(route('reports.inventory'))->assertOk();
        $this->actingAs($seniorManager)->get(route('attendance.register'))->assertOk();
        $this->actingAs($seniorManager)->get(route('attendance.export_payroll', ['month' => now()->format('Y-m')]))->assertOk();

        // Company Admin can view Audit History
        $this->actingAs($this->companyAdmin)->get(route('reports.audit_logs'))->assertOk();

        // Office Staff can view monthly attendance register but CANNOT export attendance CSV by default
        $this->actingAs($officeStaff)->get(route('attendance.register'))->assertOk();
        $this->actingAs($officeStaff)->get(route('attendance.export_payroll', ['month' => now()->format('Y-m')]))->assertForbidden();
    }
}
