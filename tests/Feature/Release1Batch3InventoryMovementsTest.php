<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
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
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Release1Batch3InventoryMovementsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private User $seniorManager;

    private User $warehouseManager;

    private User $siteManagerA;

    private User $officeStaff;

    private Project $project;

    private Site $siteA;

    private Site $siteB;

    private Warehouse $centralWarehouse;

    private Warehouse $yardA;

    private Warehouse $yardB;

    private Material $cement;

    private Material $steel;

    private WorkOrder $workOrderA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Apex Infra Builders Pvt Ltd',
            'code' => 'APEX-01',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->otherCompany = Company::create([
            'name' => 'Other Tenant Corp',
            'code' => 'OTHR',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        TenantContext::setCompanyId($this->company->id);

        $defaults = PermissionCatalog::defaultRolePermissions();
        $roles = [];
        foreach (['company_admin', 'senior_manager', 'warehouse_manager', 'site_manager', 'office_staff'] as $slug) {
            $roles[$slug] = Role::create([
                'company_id' => $this->company->id,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'permissions' => $defaults[$slug],
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-APX-01',
            'name' => 'Apex Metro Package 4',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-APX-A',
            'name' => 'Casting Yard Alpha',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-APX-B',
            'name' => 'Viaduct Span Beta',
            'status' => 'active',
        ]);

        $this->admin = $this->makeUser('Apex Admin', 'admin@apex.test', $roles['company_admin'], false);
        $this->seniorManager = $this->makeUser('Apex Senior Manager', 'sm@apex.test', $roles['senior_manager'], false);
        $this->warehouseManager = $this->makeUser('Apex Storekeeper', 'wm@apex.test', $roles['warehouse_manager'], false);
        $this->siteManagerA = $this->makeUser('Site Manager Alpha', 'sma@apex.test', $roles['site_manager'], true, [$this->siteA->id]);
        $this->officeStaff = $this->makeUser('Apex Office Staff', 'office@apex.test', $roles['office_staff'], false);

        $this->centralWarehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Apex Central Depot',
            'code' => 'WH-CENTRAL',
            'location_type' => 'central_warehouse',
            'site_id' => null,
            'is_active' => true,
        ]);

        $this->yardA = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Alpha Site Yard',
            'code' => 'WH-YARD-A',
            'location_type' => 'site_yard',
            'site_id' => $this->siteA->id,
            'is_active' => true,
        ]);

        $this->yardB = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Beta Site Yard',
            'code' => 'WH-YARD-B',
            'location_type' => 'site_yard',
            'site_id' => $this->siteB->id,
            'is_active' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Structural Materials',
            'code' => 'STRUCT',
        ]);

        $this->cement = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'OPC 53 Grade Cement',
            'code' => 'MAT-CEM-53',
            'unit_of_measure' => 'Bags',
            'minimum_stock_level' => 25,
            'standard_unit_cost' => 380,
            'is_active' => true,
        ]);

        $this->steel = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'TMT Fe550D 16mm Rebar',
            'code' => 'MAT-TMT-16',
            'unit_of_measure' => 'MT',
            'minimum_stock_level' => 5,
            'standard_unit_cost' => 62000,
            'is_active' => true,
        ]);

        $this->workOrderA = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'order_number' => 'WO-APX-2026-001',
            'title' => 'Alpha Pile Cap Works',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeUser(string $name, string $email, Role $role, bool $restricted, array $siteIds = []): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Password#2026'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => $restricted,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
            'restrict_to_assigned_sites' => $restricted,
        ]);

        if (! empty($siteIds)) {
            $user->sites()->sync($siteIds);
        }

        return $user;
    }

    public function test_dynamic_company_document_prefixes_and_full_issue_transfer_return_lifecycle(): void
    {
        // 1. Create PR -> dynamic PR-APEX prefix (not hardcoded PR-NSD)
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.store_request'), [
                'site_id' => $this->siteA->id,
                'warehouse_id' => $this->centralWarehouse->id,
                'urgency' => 'normal',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity' => 200, 'estimated_unit_cost' => 380],
                    ['material_id' => $this->steel->id, 'quantity' => 30, 'estimated_unit_cost' => 62000],
                ],
            ])
            ->assertRedirect(route('procurement.requests'));

        $pr = PurchaseRequest::firstOrFail();
        $this->assertStringStartsWith('PR-APEX-', $pr->request_number);

        // 2. Senior Manager approves PR and issues PO -> dynamic PO-APEX prefix
        $this->actingAs($this->seniorManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.approve_request', $pr), ['comments' => 'Approved for casting'])
            ->assertRedirect();

        $this->actingAs($this->seniorManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.store_po', $pr), [
                'supplier_name' => 'Apex Cement & Steel Traders',
                'unit_prices' => [
                    $this->cement->id => 380,
                    $this->steel->id => 62000,
                ],
            ])
            ->assertRedirect(route('procurement.orders'));

        $po = PurchaseOrder::firstOrFail();
        $this->assertStringStartsWith('PO-APEX-', $po->po_number);

        // 3. Receive Goods into Central Warehouse -> dynamic GRN-APEX prefix
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('procurement.confirm_receipt', $po), [
                'destination_warehouse_id' => $this->centralWarehouse->id,
                'challan_or_invoice_no' => 'INV-APX-9001',
                'received' => [
                    $this->cement->id => 200,
                    $this->steel->id => 30,
                ],
            ])
            ->assertRedirect(route('procurement.orders'));

        $grn = InventoryTransaction::where('transaction_type', 'goods_receipt')->firstOrFail();
        $this->assertStringStartsWith('GRN-APEX-', $grn->transaction_number);

        // 4. Stock Issue: 80 Bags Cement from Central Warehouse to Site A (linked to Work Order A)
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.issue'), [
                'source_warehouse_id' => $this->centralWarehouse->id,
                'site_id' => $this->siteA->id,
                'work_order_id' => $this->workOrderA->id,
                'challan_or_invoice_no' => 'ISS-SLIP-001',
                'notes' => 'Pile cap concreting batch 1',
                'submission_token' => 'tok-issue-001',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity' => 80],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory.stocks'));

        $issueTx = InventoryTransaction::where('transaction_type', 'warehouse_issue')->firstOrFail();
        $this->assertStringStartsWith('ISS-APEX-', $issueTx->transaction_number);
        $this->assertSame($this->workOrderA->id, (int) $issueTx->work_order_id);
        $this->assertSame(120.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));

        // 5. Stock Transfer: 50 Bags Cement from Central Warehouse to Alpha Site Yard
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.transfer'), [
                'source_warehouse_id' => $this->centralWarehouse->id,
                'destination_warehouse_id' => $this->yardA->id,
                'challan_or_invoice_no' => 'GP-TRF-001',
                'submission_token' => 'tok-transfer-001',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity' => 50],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory.stocks'));

        $trfTx = InventoryTransaction::where('transaction_type', 'inter_site_transfer')->firstOrFail();
        $this->assertStringStartsWith('TRF-APEX-', $trfTx->transaction_number);
        $this->assertSame(70.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));
        $this->assertSame(50.0, (float) InventoryStock::where('warehouse_id', $this->yardA->id)->where('material_id', $this->cement->id)->value('quantity'));

        // 6. Site Return: Site Manager Alpha returns 15 unused Bags from Site A into Alpha Site Yard
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.return'), [
                'site_id' => $this->siteA->id,
                'destination_warehouse_id' => $this->yardA->id,
                'work_order_id' => $this->workOrderA->id,
                'challan_or_invoice_no' => 'RET-SLIP-001',
                'submission_token' => 'tok-return-001',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity' => 15],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory.stocks'));

        $retTx = InventoryTransaction::where('transaction_type', 'site_return')->firstOrFail();
        $this->assertStringStartsWith('RET-APEX-', $retTx->transaction_number);
        $this->assertSame(65.0, (float) InventoryStock::where('warehouse_id', $this->yardA->id)->where('material_id', $this->cement->id)->value('quantity'));
    }

    public function test_negative_stock_is_prevented_and_multi_item_failure_rolls_back_atomically(): void
    {
        InventoryStock::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'material_id' => $this->cement->id,
            'quantity' => 40.00,
            'reserved_quantity' => 0.00,
        ]);

        InventoryStock::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'material_id' => $this->steel->id,
            'quantity' => 10.00,
            'reserved_quantity' => 0.00,
        ]);

        // Multi-item issue where item 1 (25 Cement <= 40) succeeds, but item 2 (25 Steel > 10) exceeds on-hand stock
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->from(route('inventory.stocks'))
            ->post(route('inventory.movements.issue'), [
                'source_warehouse_id' => $this->centralWarehouse->id,
                'site_id' => $this->siteA->id,
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity' => 25],
                    ['material_id' => $this->steel->id, 'quantity' => 25],
                ],
            ])
            ->assertRedirect(route('inventory.stocks'))
            ->assertSessionHasErrors(['movement']);

        // Cement MUST remain untouched at 40.00 and zero transactions must exist!
        $this->assertSame(40.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));
        $this->assertSame(10.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->steel->id)->value('quantity'));
        $this->assertSame(0, InventoryTransaction::count());

        // Transfer exceeding source stock also fails cleanly without creating destination stock
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->from(route('inventory.stocks'))
            ->post(route('inventory.movements.transfer'), [
                'source_warehouse_id' => $this->centralWarehouse->id,
                'destination_warehouse_id' => $this->yardA->id,
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity' => 45],
                ],
            ])
            ->assertRedirect(route('inventory.stocks'))
            ->assertSessionHasErrors(['movement']);

        $this->assertSame(40.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));
        $this->assertSame(0.0, (float) (InventoryStock::where('warehouse_id', $this->yardA->id)->where('material_id', $this->cement->id)->value('quantity') ?? 0.0));
    }

    public function test_stock_adjustment_maker_checker_approval_rejection_and_audit_trail(): void
    {
        InventoryStock::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'material_id' => $this->cement->id,
            'quantity' => 100.00,
            'reserved_quantity' => 0.00,
        ]);

        // 1. Adjustment that would make stock negative (-150 on 100) is rejected immediately
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->from(route('inventory.stocks'))
            ->post(route('inventory.movements.adjustment'), [
                'warehouse_id' => $this->centralWarehouse->id,
                'adjustment_reason' => 'Invalid massive write-off attempt',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity_delta' => -150],
                ],
            ])
            ->assertRedirect(route('inventory.stocks'))
            ->assertSessionHasErrors(['movement']);

        // 2. Valid adjustment request (-8 Bags damaged by rain) stays pending_confirmation without changing stock
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.adjustment'), [
                'warehouse_id' => $this->centralWarehouse->id,
                'adjustment_reason' => 'Monsoon seepage damaged 8 bags in bay 2',
                'challan_or_invoice_no' => 'AUD-2026-01',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity_delta' => -8],
                ],
            ])
            ->assertSessionHasNoErrors();

        $adj = InventoryTransaction::where('transaction_type', 'stock_adjustment')->firstOrFail();
        $this->assertStringStartsWith('ADJ-APEX-', $adj->transaction_number);
        $this->assertSame('pending_confirmation', $adj->status);
        $this->assertSame(100.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));

        // 3. Requester (Warehouse Manager) CANNOT approve or reject their own adjustment (maker-checker separation)
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.adjustments.approve', $adj))
            ->assertForbidden();

        // 4. Senior Manager approves -> stock drops to 92.00 and AuditLog is recorded
        $this->actingAs($this->seniorManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.adjustments.approve', $adj))
            ->assertSessionHasNoErrors();

        $adj->refresh();
        $this->assertSame('confirmed', $adj->status);
        $this->assertSame($this->seniorManager->id, (int) $adj->confirmed_by);
        $this->assertSame(92.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));

        $this->assertTrue(
            AuditLog::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('action', 'inventory.adjustment_approved')
                ->exists()
        );

        // 5. Second adjustment request rejected by Senior Manager -> stock stays at 92.00
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.adjustment'), [
                'warehouse_id' => $this->centralWarehouse->id,
                'adjustment_reason' => 'Unverified recount difference',
                'items' => [
                    ['material_id' => $this->cement->id, 'quantity_delta' => 5],
                ],
            ])
            ->assertSessionHasNoErrors();

        $adj2 = InventoryTransaction::where('transaction_type', 'stock_adjustment')->latest('id')->firstOrFail();

        $this->actingAs($this->seniorManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.adjustments.reject', $adj2), [
                'rejection_reason' => 'Recount physical bags with supervisor present first.',
            ])
            ->assertSessionHasNoErrors();

        $adj2->refresh();
        $this->assertSame('rejected', $adj2->status);
        $this->assertSame('Recount physical bags with supervisor present first.', $adj2->rejection_reason);
        $this->assertSame(92.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));
    }

    public function test_unauthorized_site_role_and_cross_tenant_movements_are_blocked(): void
    {
        InventoryStock::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->yardB->id,
            'material_id' => $this->cement->id,
            'quantity' => 50.00,
            'reserved_quantity' => 0.00,
        ]);

        // Office Staff without inventory.issue is blocked (403)
        $this->actingAs($this->officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.issue'), [
                'source_warehouse_id' => $this->yardB->id,
                'site_id' => $this->siteB->id,
                'items' => [['material_id' => $this->cement->id, 'quantity' => 5]],
            ])
            ->assertForbidden();

        // Site Manager A (assigned ONLY to Site A) cannot issue from Beta Site Yard (403)
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.issue'), [
                'source_warehouse_id' => $this->yardB->id,
                'site_id' => $this->siteA->id,
                'items' => [['material_id' => $this->cement->id, 'quantity' => 5]],
            ])
            ->assertForbidden();

        // Site Manager A cannot issue from Alpha Site Yard to unassigned Site B (403)
        $this->actingAs($this->siteManagerA)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.issue'), [
                'source_warehouse_id' => $this->yardA->id,
                'site_id' => $this->siteB->id,
                'items' => [['material_id' => $this->cement->id, 'quantity' => 5]],
            ])
            ->assertForbidden();

        // Cross-tenant warehouse ID rejected by validation (422)
        $foreignWarehouse = Warehouse::withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Foreign Depot',
            'code' => 'WH-FOREIGN',
            'location_type' => 'central_warehouse',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.issue'), [
                'source_warehouse_id' => $foreignWarehouse->id,
                'site_id' => $this->siteA->id,
                'items' => [['material_id' => $this->cement->id, 'quantity' => 5]],
            ])
            ->assertSessionHasErrors(['source_warehouse_id']);
    }

    public function test_duplicate_submission_token_and_duplicate_reference_slip_are_blocked(): void
    {
        InventoryStock::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'material_id' => $this->cement->id,
            'quantity' => 100.00,
            'reserved_quantity' => 0.00,
        ]);

        $payload = [
            'source_warehouse_id' => $this->centralWarehouse->id,
            'site_id' => $this->siteA->id,
            'challan_or_invoice_no' => 'ISS-UNIQ-77',
            'submission_token' => 'idempotent-token-77',
            'items' => [
                ['material_id' => $this->cement->id, 'quantity' => 30],
            ],
        ];

        // First submission succeeds -> 70 remaining
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('inventory.movements.issue'), $payload)
            ->assertRedirect(route('inventory.stocks'));

        // Second submission with same submission_token is rejected without double-deducting
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->from(route('inventory.stocks'))
            ->post(route('inventory.movements.issue'), $payload)
            ->assertRedirect(route('inventory.stocks'))
            ->assertSessionHasErrors(['movement']);

        // Third submission with new token but duplicate slip 'ISS-UNIQ-77' is also rejected
        $payload['submission_token'] = 'idempotent-token-78';
        $this->actingAs($this->warehouseManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->from(route('inventory.stocks'))
            ->post(route('inventory.movements.issue'), $payload)
            ->assertRedirect(route('inventory.stocks'))
            ->assertSessionHasErrors(['movement']);

        $this->assertSame(70.0, (float) InventoryStock::where('warehouse_id', $this->centralWarehouse->id)->where('material_id', $this->cement->id)->value('quantity'));
        $this->assertSame(1, InventoryTransaction::where('transaction_type', 'warehouse_issue')->count());
    }
}
