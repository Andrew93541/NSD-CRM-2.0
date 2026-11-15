<?php

namespace Tests\Feature;

use App\Models\AttendanceLock;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\InventoryStock;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Security\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Release1Batch2MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected User $adminA;

    protected User $seniorA;

    protected User $siteManagerA;

    protected User $officeStaffA;

    protected Project $projectA;

    protected Site $siteA;

    protected Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Alpha Civil Infrastructure Ltd',
            'code' => 'ALPHA',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Beta Roads Corp',
            'code' => 'BETA',
            'is_active' => true,
        ]);

        $defaults = PermissionCatalog::defaultRolePermissions();

        $adminRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => $defaults['company_admin'],
        ]);

        $seniorRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => array_merge($defaults['senior_manager'], ['attendance.approve']),
        ]);

        $siteMgrRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => $defaults['site_manager'],
        ]);

        $officeRoleA = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Office Staff',
            'slug' => 'office_staff',
            'permissions' => $defaults['office_staff'],
        ]);

        $this->projectA = Project::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'code' => 'PRJ-ALPHA-01',
            'name' => 'Alpha Expressway Package 1',
            'status' => 'active',
        ]);

        $projectB = Project::withoutGlobalScopes()->create([
            'company_id' => $this->companyB->id,
            'code' => 'PRJ-BETA-01',
            'name' => 'Beta Bridge Project',
            'status' => 'active',
        ]);

        $this->siteA = Site::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->projectA->id,
            'code' => 'SITE-A1',
            'name' => 'Alpha Package Yard 1',
            'status' => 'active',
        ]);

        $this->siteB = Site::withoutGlobalScopes()->create([
            'company_id' => $this->companyB->id,
            'project_id' => $projectB->id,
            'code' => 'SITE-B1',
            'name' => 'Beta Yard 1',
            'status' => 'active',
        ]);

        $this->adminA = User::factory()->create(['is_active' => true]);
        $this->adminA->companies()->attach($this->companyA->id, [
            'role_id' => $adminRoleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->seniorA = User::factory()->create(['is_active' => true]);
        $this->seniorA->companies()->attach($this->companyA->id, [
            'role_id' => $seniorRoleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->siteManagerA = User::factory()->create(['is_active' => true]);
        $this->siteManagerA->companies()->attach($this->companyA->id, [
            'role_id' => $siteMgrRoleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
        $this->siteManagerA->sites()->attach($this->siteA->id);

        $this->officeStaffA = User::factory()->create(['is_active' => true]);
        $this->officeStaffA->companies()->attach($this->companyA->id, [
            'role_id' => $officeRoleA->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
        $this->officeStaffA->sites()->attach($this->siteA->id);
    }

    public function test_company_settings_profile_signature_and_organization_switcher_enforce_boundaries(): void
    {
        Storage::fake('local');

        // Non-admin cannot view or update company settings
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('admin.company.edit'))
            ->assertForbidden();

        // Admin updates company settings + uploads PNG stamp
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $stamp = UploadedFile::fake()->createWithContent('signatory.png', $pngContent);

        $this->actingAs($this->adminA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->put(route('admin.company.update'), [
                'name' => 'Alpha Civil Infrastructure Pvt Ltd',
                'email' => 'billing@alphacivil.example',
                'phone' => '+91 9820011223',
                'registered_address' => 'Plot 42, MIDC, Navi Mumbai',
                'gstin' => '27AABCA1234F1Z5',
                'pan' => 'AABCA1234F',
                'vendor_code' => 'VEND-ALPHA-99',
                'bank_name' => 'HDFC Bank',
                'bank_account_no' => '50200099887766',
                'bank_ifsc' => 'HDFC0000123',
                'bank_branch' => 'Vashi Corporate Branch',
                'invoice_prefix' => 'ALPHA-INV',
                'invoice_starting_number' => 101,
                'invoice_rounding_mode' => 'rupee',
                'work_order_terms_template' => 'Standard retention 5%.',
                'signature' => $stamp,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.company.edit'));

        $this->companyA->refresh();
        $this->assertSame('Alpha Civil Infrastructure Pvt Ltd', $this->companyA->name);
        $this->assertSame('27AABCA1234F1Z5', $this->companyA->gstin);
        $this->assertSame('ALPHA-INV', $this->companyA->invoice_prefix);
        $this->assertSame('rupee', $this->companyA->invoice_rounding_mode);
        $this->assertNotNull($this->companyA->signature_path);
        Storage::disk('local')->assertExists($this->companyA->signature_path);

        // AuditLog redacts bank_account_no
        $audit = AuditLog::withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->where('action', 'company.settings_updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('[REDACTED]', $audit->new_values['bank_account_no'] ?? null);

        // Admin streams private signature; Site Manager is forbidden
        $this->actingAs($this->adminA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('admin.company.signature'))
            ->assertOk();

        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('admin.company.signature'))
            ->assertForbidden();

        // Unauthorized switch to Company B is blocked
        $this->actingAs($this->adminA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('switch_company'), ['company_id' => $this->companyB->id])
            ->assertForbidden();

        // Grant membership in Company B -> switcher dropdown renders and switches cleanly
        $adminRoleB = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);
        $this->adminA->companies()->attach($this->companyB->id, [
            'role_id' => $adminRoleB->id,
            'status' => 'active',
            'is_primary' => false,
        ]);

        $this->actingAs($this->adminA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('topbar-company-switcher', false)
            ->assertSee('Beta Roads Corp');

        $this->actingAs($this->adminA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('switch_company'), ['company_id' => $this->companyB->id])
            ->assertSessionHas('current_company_id', $this->companyB->id);
    }

    public function test_warehouses_categories_and_materials_crud_prevent_cross_tenant_links_and_referenced_deletes(): void
    {
        // Office Staff cannot create warehouses
        $this->actingAs($this->officeStaffA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('inventory.warehouses.store'), [
                'name' => 'Unauthorized Yard',
                'code' => 'WH-UNAUTH',
                'location_type' => 'central_warehouse',
            ])
            ->assertForbidden();

        // Cross-tenant site_id rejected when Senior Manager creates site yard
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('inventory.warehouses.store'), [
                'name' => 'Cross Tenant Yard',
                'code' => 'WH-CROSS',
                'location_type' => 'site_yard',
                'site_id' => $this->siteB->id,
            ])
            ->assertSessionHasErrors('site_id');

        // Senior Manager creates warehouse, category, and material
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('inventory.warehouses.store'), [
                'name' => 'Alpha Site Yard 1',
                'code' => 'wh-a1',
                'location_type' => 'site_yard',
                'site_id' => $this->siteA->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('inventory.warehouses'));

        $warehouse = Warehouse::withoutGlobalScopes()->where('company_id', $this->companyA->id)->where('code', 'WH-A1')->firstOrFail();

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('inventory.categories.store'), [
                'name' => 'Reinforcement Steel',
                'code' => 'cat-steel',
            ])
            ->assertRedirect(route('inventory.materials'));

        $category = MaterialCategory::withoutGlobalScopes()->where('company_id', $this->companyA->id)->where('code', 'CAT-STEEL')->firstOrFail();

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('inventory.materials.store'), [
                'category_id' => $category->id,
                'name' => 'TMT Fe550D 16mm Bars',
                'code' => 'mat-tmt-16',
                'unit_of_measure' => 'MT',
                'hsn_code' => '721420',
                'minimum_stock_level' => 5,
                'standard_unit_cost' => 56500,
                'is_active' => 1,
            ])
            ->assertRedirect(route('inventory.materials'));

        $material = Material::withoutGlobalScopes()->where('company_id', $this->companyA->id)->where('code', 'MAT-TMT-16')->firstOrFail();
        $this->assertSame('721420', $material->hsn_code);

        // Category with material cannot be deleted
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('inventory.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        // Seed stock -> neither warehouse nor material can be hard-deleted
        InventoryStock::create([
            'company_id' => $this->companyA->id,
            'warehouse_id' => $warehouse->id,
            'material_id' => $material->id,
            'quantity' => 12.5,
        ]);

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('inventory.warehouses.destroy', $warehouse))
            ->assertSessionHasErrors('warehouse');

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('inventory.materials.destroy', $material))
            ->assertSessionHasErrors('material');

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
        $this->assertDatabaseHas('materials', ['id' => $material->id]);
    }

    public function test_vendor_directory_links_to_purchase_orders_and_blocks_referenced_deletion(): void
    {
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('procurement.vendors.store'), [
                'name' => 'UltraTech Cement Ltd',
                'vendor_type' => 'material_supplier',
                'contact_person' => 'Rohan Kulkarni',
                'phone' => '+91 9822110099',
                'gstin' => '27AABCU1234A1Z5',
                'payment_terms_days' => 45,
                'bank_account_no' => '998877665544',
                'bank_ifsc' => 'ICIC0000111',
                'is_active' => 1,
            ])
            ->assertRedirect(route('procurement.vendors'));

        $vendor = Vendor::withoutGlobalScopes()->where('company_id', $this->companyA->id)->firstOrFail();
        $this->assertSame('VEND-001', $vendor->code);

        $cat = MaterialCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Cement',
            'code' => 'CEM',
        ]);
        $mat = Material::create([
            'company_id' => $this->companyA->id,
            'category_id' => $cat->id,
            'name' => 'OPC 53 Cement',
            'code' => 'OPC53',
            'unit_of_measure' => 'Bags',
            'minimum_stock_level' => 50,
            'standard_unit_cost' => 380,
            'is_active' => true,
        ]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->siteA->id,
            'request_number' => 'PR-ALPHA-0001',
            'requested_by' => $this->siteManagerA->id,
            'approved_by' => $this->seniorA->id,
            'urgency' => 'normal',
            'status' => 'approved',
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'material_id' => $mat->id,
            'quantity_requested' => 100,
            'estimated_unit_cost' => 380,
        ]);

        // Generate PO using vendor_id without manual supplier_name -> auto-populated from Vendor
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('procurement.store_po', $pr), [
                'vendor_id' => $vendor->id,
                'supplier_name' => '',
                'unit_prices' => [$mat->id => 375],
            ])
            ->assertRedirect(route('procurement.orders'));

        $po = PurchaseOrder::withoutGlobalScopes()->where('purchase_request_id', $pr->id)->firstOrFail();
        $this->assertSame($vendor->id, $po->vendor_id);
        $this->assertSame('UltraTech Cement Ltd', $po->supplier_name);
        $this->assertSame('+91 9822110099', $po->supplier_phone);

        // Referenced vendor cannot be hard-deleted
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('procurement.vendors.destroy', $vendor))
            ->assertSessionHasErrors('vendor');
    }

    public function test_subcontractors_and_clients_crud_enforce_referential_integrity(): void
    {
        // Subcontractor create + worker link protection
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('workforce.subcontractors.store'), [
                'name' => 'Apex Shuttering Works',
                'phone' => '9811223344',
                'is_active' => 1,
            ])
            ->assertRedirect(route('workforce.subcontractors'));

        $sub = Subcontractor::withoutGlobalScopes()->where('company_id', $this->companyA->id)->firstOrFail();
        $this->assertSame('SUB-001', $sub->code);

        Worker::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'code' => 'SUBW-001',
            'first_name' => 'Ganesh',
            'worker_type' => 'subcontractor_worker',
            'subcontractor_id' => $sub->id,
            'assigned_site_id' => $this->siteA->id,
            'trade_designation' => 'Carpenter',
            'daily_rate' => 800,
            'status' => 'active',
        ]);

        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('workforce.subcontractors.destroy', $sub))
            ->assertSessionHasErrors('subcontractor');

        // Client create + work order link protection
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('clients.store'), [
                'name' => 'JSW Infrastructure Ltd',
                'gstin' => '27AAACJ4323N1ZG',
                'vendor_code' => '1009988',
                'is_active' => 1,
            ])
            ->assertRedirect(route('clients.index'));

        $client = Client::withoutGlobalScopes()->where('company_id', $this->companyA->id)->firstOrFail();
        $this->assertSame('CLI-001', $client->code);

        WorkOrder::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->projectA->id,
            'site_id' => $this->siteA->id,
            'client_id' => $client->id,
            'order_number' => 'WO-ALPHA-01',
            'type' => 'client_issued',
            'title' => 'Boundary Wall Construction',
            'status' => 'submitted',
            'created_by' => $this->seniorA->id,
        ]);

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('clients.destroy', $client))
            ->assertSessionHasErrors('client');
    }

    public function test_shifts_holidays_leave_types_and_leave_quota_with_holiday_exclusion(): void
    {
        // Create two shifts; setting second as default unsets first default
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.shifts.store'), [
                'site_id' => $this->siteA->id,
                'name' => 'Morning Shift',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'grace_minutes' => 15,
                'half_day_hours' => 4,
                'full_day_hours' => 8,
                'overtime_after_hours' => 8,
                'is_default' => 1,
            ]);

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.shifts.store'), [
                'site_id' => $this->siteA->id,
                'name' => 'Day General Shift',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'grace_minutes' => 10,
                'half_day_hours' => 4,
                'full_day_hours' => 8,
                'overtime_after_hours' => 8,
                'is_default' => 1,
            ]);

        $shifts = Shift::withoutGlobalScopes()->where('company_id', $this->companyA->id)->orderBy('id')->get();
        $this->assertCount(2, $shifts);
        $this->assertFalse($shifts[0]->is_default);
        $this->assertTrue($shifts[1]->is_default);

        // Add a Holiday on 2026-11-18
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.holidays.store'), [
                'site_id' => $this->siteA->id,
                'name' => 'Site Festival Holiday',
                'holiday_date' => '2026-11-18',
                'is_paid' => 1,
            ])
            ->assertSessionHasNoErrors();

        $holiday = Holiday::withoutGlobalScopes()->where('company_id', $this->companyA->id)->firstOrFail();

        // Payroll-locked holiday cannot be deleted
        AttendanceLock::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->siteA->id,
            'attendance_date' => '2026-11-18',
            'stage' => 'payroll_locked',
        ]);

        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('attendance.holidays.destroy', $holiday))
            ->assertSessionHasErrors('holiday');

        // Configure Casual Leave with 3-day annual quota
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.leave_types.store'), [
                'name' => 'Casual Leave',
                'code' => 'CL',
                'annual_quota' => 3,
                'is_paid' => 1,
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $leaveType = LeaveType::withoutGlobalScopes()->where('company_id', $this->companyA->id)->where('code', 'CL')->firstOrFail();

        $worker = Worker::withoutGlobalScopes()->create([
            'company_id' => $this->companyA->id,
            'code' => 'EMP-ALPHA-01',
            'first_name' => 'Suresh',
            'last_name' => 'Patil',
            'worker_type' => 'employee',
            'assigned_site_id' => $this->siteA->id,
            'trade_designation' => 'Site Engineer',
            'daily_rate' => 1000,
            'status' => 'active',
        ]);

        // Request leave from 2026-11-17 to 2026-11-19 (3 calendar days, 1 of which 2026-11-18 is a Holiday -> 2 working days!)
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.leaves.store'), [
                'worker_id' => $worker->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-11-17',
                'end_date' => '2026-11-19',
                'reason' => 'Family function in hometown',
            ])
            ->assertSessionHasNoErrors();

        $lr = LeaveRequest::withoutGlobalScopes()->where('worker_id', $worker->id)->firstOrFail();
        $this->assertSame(2, $lr->days_count);
        $this->assertSame($leaveType->id, $lr->leave_type_id);

        // Overlapping leave request is blocked
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.leaves.store'), [
                'worker_id' => $worker->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-11-19',
                'end_date' => '2026-11-20',
                'reason' => 'Overlapping leave attempt',
            ])
            ->assertSessionHasErrors('start_date');

        // Requesting 2 more days (total 2 + 2 = 4 > quota 3) is blocked with clear balance message
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->post(route('attendance.leaves.store'), [
                'worker_id' => $worker->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-11-24',
                'end_date' => '2026-11-25',
                'reason' => 'Exceeds annual quota',
            ])
            ->assertSessionHasErrors('leave_type_name');

        // Referenced LeaveType cannot be deleted
        $this->actingAs($this->seniorA)
            ->withSession(['current_company_id' => $this->companyA->id])
            ->delete(route('attendance.leave_types.destroy', $leaveType))
            ->assertSessionHasErrors('leave_type');
    }
}
