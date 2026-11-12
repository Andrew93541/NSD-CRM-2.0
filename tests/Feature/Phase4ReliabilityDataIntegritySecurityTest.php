<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\LeaveRequest;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase4ReliabilityDataIntegritySecurityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'code' => 'NSD-P4',
            'name' => 'NSD Reliability & Hardening Corp',
            'legal_name' => 'NSD Reliability Pvt Ltd',
            'primary_location' => 'Mumbai, Maharashtra',
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

        $this->companyAdmin = $this->createUserWithRole('Admin User', 'admin@nsd-p4.test', 'company_admin');
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

        if (!empty($sites)) {
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

    public function test_duplicate_project_and_site_codes_return_clear_validation_messages(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-NH48',
            'name' => 'NH-48 Corridor',
            'status' => 'active',
        ]);

        Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-PUNE-01',
            'name' => 'Pune Base Camp',
            'status' => 'active',
        ]);

        // Duplicate project code (case-insensitive / trimmed normalization)
        $resProject = $this->actingAs($this->companyAdmin)->post(route('projects.store'), [
            'code' => '  prj-nh48 ',
            'name' => 'Duplicate Corridor',
            'status' => 'active',
        ]);

        $resProject->assertSessionHasErrors(['code']);
        $this->assertStringContainsString(
            'already in use',
            session('errors')->first('code')
        );

        // Duplicate site code
        $resSite = $this->actingAs($this->companyAdmin)->post(route('sites.store'), [
            'project_id' => $project->id,
            'code' => 'site-pune-01',
            'name' => 'Duplicate Site',
            'status' => 'active',
        ]);

        $resSite->assertSessionHasErrors(['code']);
        $this->assertStringContainsString(
            'already in use',
            session('errors')->first('code')
        );
    }

    public function test_duplicate_active_worker_with_same_name_and_aadhaar_is_blocked(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-001',
            'name' => 'Metro Line 3',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Station 1',
            'status' => 'active',
        ]);

        $siteManager = $this->createUserWithRole('Site Manager', 'sm@nsd-p4.test', 'site_manager', [$site->id], true);

        // First worker registration succeeds
        $first = $this->actingAs($siteManager)->post(route('workforce.store'), [
            'worker_type' => 'direct_labour',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'aadhaar_last_four' => '4821',
            'trade_designation' => 'Mason',
            'daily_rate' => 750,
            'assigned_site_id' => $site->id,
        ]);
        $first->assertRedirect(route('workforce.index'));
        $this->assertEquals(1, Worker::count());

        // Accidental second submission with same name & Aadhaar last 4 is blocked
        $duplicate = $this->actingAs($siteManager)->post(route('workforce.store'), [
            'worker_type' => 'direct_labour',
            'first_name' => ' ramesh ',
            'last_name' => 'PAWAR',
            'aadhaar_last_four' => '4821',
            'trade_designation' => 'Mason',
            'daily_rate' => 750,
            'assigned_site_id' => $site->id,
        ]);
        $duplicate->assertSessionHasErrors(['aadhaar_last_four']);
        $this->assertEquals(1, Worker::count());
    }

    public function test_work_order_and_milestone_reject_mismatched_project_and_site(): void
    {
        $projectA = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-A',
            'name' => 'Project Alpha',
            'status' => 'active',
        ]);

        $projectB = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-B',
            'name' => 'Project Beta',
            'status' => 'active',
        ]);

        $siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $projectB->id,
            'code' => 'SITE-B1',
            'name' => 'Beta Site 1',
            'status' => 'active',
        ]);

        // Attempt to create a Work Order under Project A but assigned to Site B (which belongs to Project B)
        $woRes = $this->actingAs($this->companyAdmin)->post(route('work_orders.store'), [
            'project_id' => $projectA->id,
            'site_id' => $siteB->id,
            'order_type' => 'internal',
            'title' => 'Excavation Package',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'description' => 'Earthwork',
                    'unit' => 'M3',
                    'ordered_quantity' => 50,
                    'rate' => 300,
                ],
            ],
        ]);

        $woRes->assertSessionHasErrors(['site_id']);
        $this->assertEquals(0, WorkOrder::count());

        // Attempt to create a Milestone on Project A linked to Site B
        $msRes = $this->actingAs($this->companyAdmin)->post(route('projects.milestones.store', $projectA), [
            'site_id' => $siteB->id,
            'title' => 'Foundation Completion',
            'status' => 'in_progress',
            'percent_complete' => 25,
        ]);

        $msRes->assertSessionHasErrors(['site_id']);
    }

    public function test_attendance_correction_and_leave_cannot_be_reviewed_twice(): void
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

        $supervisor = $this->createUserWithRole('Supervisor', 'sup@nsd-p4.test', 'site_supervisor', [$site->id], true);
        $approver = $this->createUserWithRole(
            'Authorized Manager',
            'mgr@nsd-p4.test',
            'senior_manager',
            [$site->id],
            false,
            ['attendance.approve' => 'allow']
        );

        $worker = Worker::create([
            'company_id' => $this->company->id,
            'code' => 'W-LAB-2026-0001',
            'worker_type' => 'direct_labour',
            'first_name' => 'Sunil',
            'last_name' => 'Jadhav',
            'trade_designation' => 'Helper',
            'daily_rate' => 600,
            'assigned_site_id' => $site->id,
            'status' => 'active',
        ]);

        $attendance = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $site->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'attendance_date' => '2026-11-10',
            'status' => 'absent',
            'worked_hours' => 0,
            'overtime_hours' => 0,
            'submitted_by' => $supervisor->id,
        ]);

        $correction = AttendanceCorrection::create([
            'company_id' => $this->company->id,
            'daily_attendance_id' => $attendance->id,
            'requested_by' => $supervisor->id,
            'previous_status' => 'absent',
            'proposed_status' => 'present',
            'previous_clock_in' => null,
            'proposed_clock_in' => '09:00:00',
            'previous_clock_out' => null,
            'proposed_clock_out' => '18:00:00',
            'previous_worked_hours' => 0,
            'proposed_worked_hours' => 9,
            'previous_overtime_hours' => 0,
            'proposed_overtime_hours' => 1,
            'reason' => 'Worker was at north trench',
            'status' => 'pending',
        ]);

        // First review approves the correction
        $firstReview = $this->actingAs($approver)->post(route('attendance.review_correction', $correction), [
            'action' => 'approved',
            'review_notes' => 'Verified with site log',
        ]);
        $firstReview->assertSessionHasNoErrors();
        $this->assertEquals('approved', $correction->fresh()->status);
        $this->assertEquals('present', $attendance->fresh()->status);

        // Second review attempt (trying to flip approved correction to rejected) is blocked
        $secondReview = $this->actingAs($approver)->post(route('attendance.review_correction', $correction), [
            'action' => 'rejected',
            'review_notes' => 'Accidental second click',
        ]);
        $secondReview->assertSessionHasErrors(['status']);
        $this->assertEquals('approved', $correction->fresh()->status);
        $this->assertEquals('present', $attendance->fresh()->status);

        // Leave request double-review check
        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'worker_id' => $worker->id,
            'site_id' => $site->id,
            'leave_type_name' => 'Medical Leave',
            'is_paid' => true,
            'start_date' => '2026-11-11',
            'end_date' => '2026-11-11',
            'days_count' => 1,
            'reason' => 'Clinic visit',
            'status' => 'pending',
            'requested_by' => $supervisor->id,
        ]);

        $this->actingAs($approver)->post(route('attendance.leaves.review', $leave), [
            'action' => 'rejected',
            'review_notes' => 'No medical certificate',
        ])->assertSessionHasNoErrors();

        $this->actingAs($approver)->post(route('attendance.leaves.review', $leave), [
            'action' => 'approved',
            'review_notes' => 'Trying to re-approve',
        ])->assertSessionHasErrors(['status']);

        $this->assertEquals('rejected', $leave->fresh()->status);
    }

    public function test_procurement_sequence_safety_and_goods_receipt_rollback_on_excess_quantity(): void
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
            'name' => 'Procurement Site',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'site_id' => $site->id,
            'code' => 'WH-PROC',
            'name' => 'Site Store',
            'type' => 'site_store',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Aggregates & Cement',
            'code' => 'CAT-CEM',
        ]);

        $material = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'code' => 'MAT-OPC53',
            'name' => 'OPC 53 Grade Cement',
            'unit_of_measure' => 'Bags',
            'standard_rate' => 380,
            'reorder_level' => 50,
            'is_active' => true,
        ]);

        $seniorManager = $this->createUserWithRole('Senior Manager', 'sr@nsd-p4.test', 'senior_manager');

        // Create PR as Company Admin
        $this->actingAs($this->companyAdmin)->post(route('procurement.store_request'), [
            'site_id' => $site->id,
            'warehouse_id' => $warehouse->id,
            'urgency' => 'normal',
            'items' => [
                ['material_id' => $material->id, 'quantity' => 100, 'purpose_notes' => 'Pier cap'],
            ],
        ])->assertRedirect(route('procurement.requests'));

        $pr = PurchaseRequest::firstOrFail();

        // Attempting to issue PO before PR is approved is blocked at policy level (403)
        $prematurePo = $this->actingAs($seniorManager)->post(route('procurement.store_po', $pr), [
            'supplier_name' => 'UltraTech Suppliers',
            'unit_prices' => [$material->id => 375],
        ]);
        $prematurePo->assertForbidden();
        $this->assertEquals(0, PurchaseOrder::count());

        // Approve PR, then attempt second approval -> blocked at policy level (403)
        $this->actingAs($seniorManager)->post(route('procurement.approve_request', $pr))->assertSessionHasNoErrors();
        $this->actingAs($seniorManager)->post(route('procurement.approve_request', $pr))->assertForbidden();

        // Issue PO with negative unit price -> blocked by validation
        $negPo = $this->actingAs($seniorManager)->post(route('procurement.store_po', $pr), [
            'supplier_name' => 'UltraTech Suppliers',
            'unit_prices' => [$material->id => -10],
        ]);
        $negPo->assertSessionHasErrors(["unit_prices.{$material->id}"]);

        // Issue valid PO (100 bags)
        $this->actingAs($seniorManager)->post(route('procurement.store_po', $pr), [
            'supplier_name' => 'UltraTech Suppliers',
            'unit_prices' => [$material->id => 375],
        ])->assertRedirect(route('procurement.orders'));

        $po = PurchaseOrder::firstOrFail();

        // Attempt to receive 150 bags (> 100 ordered) -> rolls back transaction and returns validation error
        $excessGrn = $this->actingAs($seniorManager)->post(route('procurement.confirm_receipt', $po), [
            'destination_warehouse_id' => $warehouse->id,
            'received' => [$material->id => 150],
            'challan_or_invoice_no' => 'CH-EXCESS-01',
        ]);
        $excessGrn->assertSessionHasErrors(['received']);
        $this->assertEquals(0, InventoryStock::count());
        $this->assertEquals('issued', $po->fresh()->status);

        // Receive valid 60 bags -> stock updated to 60 and PO status is partially_received
        $validGrn = $this->actingAs($seniorManager)->post(route('procurement.confirm_receipt', $po), [
            'destination_warehouse_id' => $warehouse->id,
            'received' => [$material->id => 60],
            'challan_or_invoice_no' => 'CH-VALID-01',
        ]);
        $validGrn->assertRedirect(route('procurement.orders'));
        $this->assertEquals(60.0, (float) InventoryStock::where('warehouse_id', $warehouse->id)->where('material_id', $material->id)->value('quantity'));
        $this->assertEquals('partially_received', $po->fresh()->status);

        // Verify sequence table tracked PR, PO, and GRN
        $this->assertEquals(3, DB::table('worker_sequences')->where('company_id', $this->company->id)->whereIn('prefix', ['PR-NSD', 'PO-NSD', 'GRN-NSD'])->count());
    }

    public function test_site_restricted_senior_manager_cannot_access_unassigned_sites_in_dropdowns_or_company_wide_actions(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-SEC',
            'name' => 'Security Audit Project',
            'status' => 'active',
        ]);

        $assignedSite = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-ASSIGNED',
            'name' => 'Assigned Sector Alpha',
            'status' => 'active',
        ]);

        $unassignedSite = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-SECRET',
            'name' => 'Unassigned Sector Omega',
            'status' => 'active',
        ]);

        // Senior Manager with restrict_to_assigned_sites = true assigned ONLY to $assignedSite
        $restrictedSenior = $this->createUserWithRole(
            'Restricted Senior Mgr',
            'restricted.sr@nsd-p4.test',
            'senior_manager',
            [$assignedSite->id],
            true
        );

        // 1. Workforce create page must only list Assigned Sector Alpha, NOT Unassigned Sector Omega
        $wfCreate = $this->actingAs($restrictedSenior)->get(route('workforce.create'));
        $wfCreate->assertOk();
        $wfCreate->assertSee('Assigned Sector Alpha');
        $wfCreate->assertDontSee('Unassigned Sector Omega');

        // 2. Attendance Shifts & Leaves page must only list Assigned Sector Alpha
        $shiftsPage = $this->actingAs($restrictedSenior)->get(route('attendance.shifts_leaves'));
        $shiftsPage->assertOk();
        $shiftsPage->assertSee('Assigned Sector Alpha');
        $shiftsPage->assertDontSee('Unassigned Sector Omega');

        // 3. Restricted Senior Manager cannot create a company-wide holiday (site_id = null) or holiday for unassigned site
        $this->actingAs($restrictedSenior)->post(route('attendance.holidays.store'), [
            'site_id' => null,
            'name' => 'Unauthorized Global Holiday',
            'holiday_date' => '2026-12-25',
        ])->assertForbidden();

        $this->actingAs($restrictedSenior)->post(route('attendance.holidays.store'), [
            'site_id' => $unassignedSite->id,
            'name' => 'Unauthorized Site Holiday',
            'holiday_date' => '2026-12-25',
        ])->assertForbidden();

        // 4. Restricted Senior Manager cannot generate a company-wide payroll period (site_id = null)
        $this->actingAs($restrictedSenior)->post(route('payroll.store'), [
            'title' => 'Global Payroll Attempt',
            'site_id' => null,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ])->assertForbidden();
    }
}
