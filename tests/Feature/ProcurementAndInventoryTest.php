<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Procurement\InventoryService;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class ProcurementAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $siteManager;
    protected User $seniorManager;
    protected Warehouse $warehouse;
    protected Material $cement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'NSD Co', 'code' => 'NSD', 'is_active' => true]);
        TenantContext::setCompanyId($this->company->id);

        $roleSite = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['procurement.request', 'inventory.receive'],
        ]);

        $roleSenior = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => ['procurement.approve', 'procurement.view'],
        ]);

        $this->siteManager = User::create([
            'name' => 'Site Manager',
            'email' => 'sm@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->siteManager->companies()->attach($this->company->id, ['role_id' => $roleSite->id]);

        $this->seniorManager = User::create([
            'name' => 'Senior Manager',
            'email' => 'vp@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->seniorManager->companies()->attach($this->company->id, ['role_id' => $roleSenior->id]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Central Depot',
            'code' => 'WH-C',
            'location_type' => 'central_warehouse',
            'is_active' => true,
        ]);

        $category = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Cement',
            'code' => 'CEM',
        ]);

        $this->cement = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'OPC 53 Cement',
            'code' => 'CEM-53',
            'unit_of_measure' => 'Bags',
            'minimum_stock_level' => 100,
            'standard_unit_cost' => 380,
            'is_active' => true,
        ]);
    }

    public function test_purchase_request_requires_approval_before_po_creation(): void
    {
        $service = new InventoryService();

        $pr = $service->createPurchaseRequest([
            'warehouse_id' => $this->warehouse->id,
            'urgency' => 'normal',
        ], [
            ['material_id' => $this->cement->id, 'quantity' => 200, 'estimated_unit_cost' => 380],
        ], $this->siteManager);

        $this->assertEquals('pending', $pr->status);

        // Attempting to generate PO before approval must throw exception
        $this->expectException(InvalidArgumentException::class);
        $service->createPurchaseOrder($pr, [
            'supplier_name' => 'Ambuja Cements',
        ], $this->siteManager);
    }

    public function test_emergency_purchase_requires_mandatory_justification(): void
    {
        $service = new InventoryService();

        $this->expectException(InvalidArgumentException::class);
        $service->createPurchaseRequest([
            'warehouse_id' => $this->warehouse->id,
            'urgency' => 'emergency',
            'is_emergency' => true,
            'emergency_justification' => '', // Empty justification must be rejected
        ], [
            ['material_id' => $this->cement->id, 'quantity' => 50],
        ], $this->siteManager);
    }

    public function test_goods_receipt_note_increments_stock_and_updates_purchase_order(): void
    {
        $service = new InventoryService();

        // 1. Requisition & Approval
        $pr = $service->createPurchaseRequest([
            'warehouse_id' => $this->warehouse->id,
            'urgency' => 'normal',
        ], [
            ['material_id' => $this->cement->id, 'quantity' => 150, 'estimated_unit_cost' => 380],
        ], $this->siteManager);

        $service->approvePurchaseRequest($pr, $this->seniorManager, 'Approved standard order');

        // 2. Issue PO
        $po = $service->createPurchaseOrder($pr, [
            'supplier_name' => 'UltraTech Cement Ltd',
            'supplier_phone' => '+91 98888 11111',
            'unit_prices' => [$this->cement->id => 380],
        ], $this->seniorManager);

        $this->assertEquals('issued', $po->status);

        // Initial stock is 0
        $initialStock = InventoryStock::where('warehouse_id', $this->warehouse->id)->where('material_id', $this->cement->id)->first();
        $this->assertNull($initialStock);

        // 3. Confirm Goods Receipt Note (GRN) for 150 Bags
        $tx = $service->recordGoodsReceipt(
            $po,
            $this->warehouse->id,
            [$this->cement->id => 150],
            $this->siteManager,
            'CHALLAN-9921'
        );

        $this->assertEquals('goods_receipt', $tx->transaction_type);
        $this->assertEquals('confirmed', $tx->status);

        // Verify stock incremented
        $updatedStock = InventoryStock::where('warehouse_id', $this->warehouse->id)->where('material_id', $this->cement->id)->first();
        $this->assertNotNull($updatedStock);
        $this->assertEquals(150.00, $updatedStock->quantity);

        // Verify PO marked fully received
        $po->refresh();
        $this->assertEquals('received', $po->status);
        $this->assertEquals(150.00, $po->items->first()->quantity_received);
    }

    public function test_duplicate_challan_receipt_is_prevented(): void
    {
        $service = new InventoryService();

        $pr = $service->createPurchaseRequest([
            'warehouse_id' => $this->warehouse->id,
            'urgency' => 'normal',
        ], [
            ['material_id' => $this->cement->id, 'quantity' => 100, 'estimated_unit_cost' => 380],
        ], $this->siteManager);

        $service->approvePurchaseRequest($pr, $this->seniorManager);
        $po = $service->createPurchaseOrder($pr, [
            'supplier_name' => 'UltraTech Cement Ltd',
            'unit_prices' => [$this->cement->id => 380],
        ], $this->seniorManager);

        // First partial receipt of 50 bags with CHALLAN-100
        $service->recordGoodsReceipt(
            $po,
            $this->warehouse->id,
            [$this->cement->id => 50],
            $this->siteManager,
            'CHALLAN-100'
        );

        // Second receipt attempting to re-use CHALLAN-100 must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has already been received');
        $service->recordGoodsReceipt(
            $po,
            $this->warehouse->id,
            [$this->cement->id => 50],
            $this->siteManager,
            'CHALLAN-100'
        );
    }

    public function test_over_receipt_beyond_ordered_quantity_is_rejected(): void
    {
        $service = new InventoryService();

        $pr = $service->createPurchaseRequest([
            'warehouse_id' => $this->warehouse->id,
            'urgency' => 'normal',
        ], [
            ['material_id' => $this->cement->id, 'quantity' => 100, 'estimated_unit_cost' => 380],
        ], $this->siteManager);

        $service->approvePurchaseRequest($pr, $this->seniorManager);
        $po = $service->createPurchaseOrder($pr, [
            'supplier_name' => 'UltraTech Cement Ltd',
            'unit_prices' => [$this->cement->id => 380],
        ], $this->seniorManager);

        // Attempting to receive 150 bags when only 100 were ordered
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds outstanding balance');
        $service->recordGoodsReceipt(
            $po,
            $this->warehouse->id,
            [$this->cement->id => 150],
            $this->siteManager,
            'CHALLAN-OVER'
        );
    }

    public function test_zero_quantity_goods_receipt_is_rejected(): void
    {
        $service = new InventoryService();

        $pr = $service->createPurchaseRequest([
            'warehouse_id' => $this->warehouse->id,
            'urgency' => 'normal',
        ], [
            ['material_id' => $this->cement->id, 'quantity' => 100, 'estimated_unit_cost' => 380],
        ], $this->siteManager);

        $service->approvePurchaseRequest($pr, $this->seniorManager);
        $po = $service->createPurchaseOrder($pr, [
            'supplier_name' => 'UltraTech Cement Ltd',
            'unit_prices' => [$this->cement->id => 380],
        ], $this->seniorManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item must have a quantity greater than zero');
        $service->recordGoodsReceipt(
            $po,
            $this->warehouse->id,
            [$this->cement->id => 0],
            $this->siteManager,
            'CHALLAN-EMPTY'
        );
    }
}
