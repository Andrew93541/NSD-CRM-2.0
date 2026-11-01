<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseEUiScreensAndWizardTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Project $project;
    private Site $siteA;
    private Site $siteB;
    private Client $client;
    private User $seniorManager;
    private User $siteManagerA;
    private User $siteManagerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction',
            'code' => 'NSD',
            'gstin' => '27AABCN1234F1Z5',
            'invoice_prefix' => 'CIVIL',
            'invoice_starting_number' => 21,
            'invoice_rounding_mode' => 'paisa',
            'is_active' => true,
        ]);

        TenantContext::setCompanyId($this->company->id);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-JSW-01',
            'name' => 'JSW Dolvi SMS-2 Civil Works',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'JSW-DOLVI-A',
            'name' => 'JSW Dolvi SMS-2 Site A',
            'city' => 'Dolvi',
            'state' => 'Maharashtra',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'JSW-DOLVI-B',
            'name' => 'JSW Dolvi Pellet Site B',
            'city' => 'Dolvi',
            'state' => 'Maharashtra',
            'status' => 'active',
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'code' => 'JSW-DOLVI',
            'name' => 'JSW STEEL LIMITED',
            'gstin' => '27AAACJ4323N1Z5',
        ]);

        $smRole = Role::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'site_manager'],
            ['name' => 'Site Manager', 'permissions' => ['work_orders.view', 'work_orders.create', 'work_orders.approve_site']]
        );

        $vpRole = Role::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'senior_manager'],
            ['name' => 'Senior Manager', 'permissions' => ['work_orders.view', 'work_orders.create', 'work_orders.approve_senior']]
        );

        $this->siteManagerA = User::factory()->create(['name' => 'Site Manager A', 'is_active' => true]);
        $this->siteManagerA->companies()->attach($this->company->id, ['role_id' => $smRole->id]);
        $this->siteA->assignedUsers()->attach($this->siteManagerA->id);

        $this->siteManagerB = User::factory()->create(['name' => 'Site Manager B', 'is_active' => true]);
        $this->siteManagerB->companies()->attach($this->company->id, ['role_id' => $smRole->id]);
        $this->siteB->assignedUsers()->attach($this->siteManagerB->id);

        $this->seniorManager = User::factory()->create(['name' => 'Senior VP', 'is_active' => true]);
        $this->seniorManager->companies()->attach($this->company->id, ['role_id' => $vpRole->id]);
    }

    public function test_work_order_consumption_bars_mobile_measurement_entry_and_ra_bill_wizard(): void
    {
        $this->actingAs($this->siteManagerA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'client_id' => $this->client->id,
            'order_type' => 'client_issued',
            'po_number' => '4700226845',
            'title' => 'JSW SMS-2 Culvert & Trench Package',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Earthwork Excavation',
                    'unit' => 'M3',
                    'ordered_quantity' => 100.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::with('items')->firstOrFail();
        $woItem = $workOrder->items->firstOrFail();

        // 1. Mobile Field Measurement Entry screen
        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.measurements.create', $workOrder))
            ->assertStatus(403);

        $measureFormRes = $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.measurements.create', $workOrder));
        $measureFormRes->assertStatus(200);
        $measureFormRes->assertSee('Mobile Field Measurement Sheet');
        $measureFormRes->assertSee('1.10');

        // Record measurement of 85 M3 (85% consumption -> Amber warning_80)
        $this->actingAs($this->siteManagerA)->post(route('work_orders.measurements.store', $workOrder), [
            'work_order_item_id' => $woItem->id,
            'measured_on' => '2026-10-12',
            'location_chainage' => 'SMS-2 Ch 0+450',
            'nos' => 1,
            'direct_quantity' => 85.000,
            'hold_quantity' => 0.000,
        ])->assertRedirect();

        // 2. Interactive RA Bill Wizard screen
        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.ra_bills.create', $workOrder))
            ->assertStatus(403);

        $wizardRes = $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.ra_bills.create', $workOrder));
        $wizardRes->assertStatus(200);
        $wizardRes->assertSee('Interactive RA Bill Wizard');
        $wizardRes->assertSee('1ST RA BILL');

        // Generate and approve 1ST RA BILL (85% consumed)
        $this->actingAs($this->siteManagerA)->post(route('work_orders.ra_bills.store', $workOrder), [
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-31',
            'rounding_mode' => 'paisa',
        ])->assertRedirect();

        $raBill = RaBill::where('work_order_id', $workOrder->id)->firstOrFail();
        $this->actingAs($this->siteManagerA)->post(route('work_orders.ra_bills.approve_site', [$workOrder, $raBill]))->assertRedirect();
        $this->actingAs($this->seniorManager)->post(route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill]))->assertRedirect();

        // 3. Verify Work Order Index & Show screens render 85.0% consumption bar and 15.000 Balance Qty
        $indexRes = $this->actingAs($this->siteManagerA)->get(route('work_orders.index'));
        $indexRes->assertStatus(200);
        $indexRes->assertSee('85.0%');
        $indexRes->assertSee('PO #4700226845');

        $showRes = $this->actingAs($this->siteManagerA)->get(route('work_orders.show', $workOrder));
        $showRes->assertStatus(200);
        $showRes->assertSee('85.0%');
        $showRes->assertSee('85.000');
        $showRes->assertSee('15.000');
        $showRes->assertSee('Launch RA Bill Wizard');
        $showRes->assertSee('Mobile Field Measurement Sheet');
    }
}

