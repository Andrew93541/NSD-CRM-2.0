<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\MeasurementEntry;
use App\Models\Project;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Operations\RaBillingService;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseBRaBillingAndCapTest extends TestCase
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
    private User $officeStaffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction',
            'code' => 'NSD',
            'gstin' => '27AABCN1234F1Z5',
            'pan' => 'AABCN1234F',
            'vendor_code' => '100984',
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
            'vendor_code' => '100984',
        ]);

        $this->seniorManager = $this->createUserWithRole('senior_manager', 'Senior VP', [
            'work_orders.view', 'work_orders.create', 'work_orders.approve_senior',
        ]);
        $this->siteManagerA = $this->createUserWithRole('site_manager', 'Site Manager A', [
            'work_orders.view', 'work_orders.create', 'work_orders.approve_site',
        ], [$this->siteA->id]);
        $this->siteManagerB = $this->createUserWithRole('site_manager', 'Site Manager B', [
            'work_orders.view', 'work_orders.create', 'work_orders.approve_site',
        ], [$this->siteB->id]);
        $this->officeStaffA = $this->createUserWithRole('office_staff', 'Office Staff A', [
            'work_orders.view', 'work_orders.create',
        ], [$this->siteA->id]);
    }

    private function createUserWithRole(string $slug, string $name, array $permissions, array $siteIds = []): User
    {
        $role = Role::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => $slug],
            ['name' => $name, 'permissions' => $permissions]
        );

        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        $user->companies()->attach($this->company->id, ['role_id' => $role->id]);

        foreach ($siteIds as $siteId) {
            Site::find($siteId)?->assignedUsers()->attach($user->id);
        }

        return $user;
    }

    public function test_compute_line_item_bill_enforces_cap_hold_and_warning_levels(): void
    {
        // Case 1: ordered = 100, previous = 60, claimed = 55, hold = 5
        // effective_claimed = max(0, 55 - 5) = 50
        // remaining_available = max(0, 100 - 60) = 40
        // current_billable = min(50, 40) = 40
        // excess = 50 - 40 = 10
        // cumulative = 60 + 40 = 100, balance = 0, warning = cap_100
        $res1 = RaBillingService::computeLineItemBill(100.0, 60.0, 55.0, 5.0, 250.0);
        $this->assertSame(50.0, $res1['effective_claimed_quantity']);
        $this->assertSame(40.0, $res1['remaining_available_quantity']);
        $this->assertSame(40.0, $res1['current_billable_quantity']);
        $this->assertSame(10.0, $res1['excess_quantity']);
        $this->assertSame(100.0, $res1['cumulative_quantity']);
        $this->assertSame(0.0, $res1['balance_quantity']);
        $this->assertSame(100.0, $res1['consumption_percent']);
        $this->assertSame('cap_100', $res1['warning_level']);
        $this->assertSame(10000.0, $res1['current_amount']);
        $this->assertSame(2500.0, $res1['excess_amount']);

        // Case 2: hold exceeds claimed -> effective_claimed = 0
        $res2 = RaBillingService::computeLineItemBill(100.0, 20.0, 10.0, 15.0, 300.0);
        $this->assertSame(0.0, $res2['effective_claimed_quantity']);
        $this->assertSame(0.0, $res2['current_billable_quantity']);
        $this->assertSame(0.0, $res2['excess_quantity']);
        $this->assertSame(20.0, $res2['cumulative_quantity']);
        $this->assertSame(80.0, $res2['balance_quantity']);
        $this->assertSame('normal', $res2['warning_level']);

        // Case 3: 80% and 90% warning thresholds
        $res80 = RaBillingService::computeLineItemBill(100.0, 50.0, 32.0, 0.0, 100.0);
        $this->assertSame(82.0, $res80['consumption_percent']);
        $this->assertSame('warning_80', $res80['warning_level']);

        $res90 = RaBillingService::computeLineItemBill(100.0, 50.0, 45.0, 0.0, 100.0);
        $this->assertSame(95.0, $res90['consumption_percent']);
        $this->assertSame('warning_90', $res90['warning_level']);
    }

    public function test_measurements_ra_bill_cap_and_revision_unlock_of_earlier_excess(): void
    {
        Storage::fake('local');

        // 1. Create JSW Service Order with Ordered Qty = 100 M3 @ ₹500/M3
        $this->actingAs($this->officeStaffA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'client_id' => $this->client->id,
            'order_type' => 'client_issued',
            'po_number' => '4700226845',
            'title' => 'JSW SMS-2 Foundation Excavation',
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'retention_percent' => 0.00,
            'items' => [
                [
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Earthwork Excavation in Soil & Soft Rock',
                    'unit' => 'M3',
                    'ordered_quantity' => 100.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::with('items')->firstOrFail();
        $woItem = $workOrder->items->firstOrFail();

        // 2. Record two site measurements in Period 1:
        // Entry 1: L=10, B=5, D=2 => 100 M3, hold = 5 M3
        // Entry 2: Direct = 25 M3, hold = 0 M3
        // Total claimed = 125 M3, hold = 5 M3 => effective_claimed = 120 M3
        $this->actingAs($this->siteManagerA)->post(route('work_orders.measurements.store', $workOrder), [
            'work_order_item_id' => $woItem->id,
            'measured_on' => '2026-10-10',
            'location_chainage' => 'SMS-2 Grid A1-A4',
            'nos' => 1,
            'length' => 10,
            'breadth' => 5,
            'depth_height' => 2,
            'hold_quantity' => 5.000,
            'photo' => UploadedFile::fake()->create('grid-a1-sheet.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($this->siteManagerA)->post(route('work_orders.measurements.store', $workOrder), [
            'work_order_item_id' => $woItem->id,
            'measured_on' => '2026-10-18',
            'location_chainage' => 'SMS-2 Grid A5-A6',
            'nos' => 1,
            'direct_quantity' => 25.000,
            'hold_quantity' => 0.000,
        ])->assertRedirect();

        $firstMeasurement = MeasurementEntry::firstOrFail();
        $this->assertSame('100.000', (string) $firstMeasurement->computed_quantity);

        // Site Manager B (unassigned site) cannot download measurement photo
        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.measurements.photo', [$workOrder, $firstMeasurement->id]))
            ->assertStatus(403);

        $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.measurements.photo', [$workOrder, $firstMeasurement->id]))
            ->assertStatus(200);

        // 3. Generate 1ST RA BILL for Oct 2026
        $this->actingAs($this->officeStaffA)->post(route('work_orders.ra_bills.store', $workOrder), [
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-31',
            'rounding_mode' => 'paisa',
        ])->assertRedirect();

        $raBill1 = RaBill::with('items')->where('work_order_id', $workOrder->id)->where('ra_number', 1)->firstOrFail();
        $this->assertSame('1ST RA BILL', $raBill1->ra_label);

        $billItem1 = $raBill1->items->firstOrFail();
        $this->assertSame('1.10', $billItem1->sr_no);
        $this->assertSame('125.000', (string) $billItem1->claimed_quantity);
        $this->assertSame('5.000', (string) $billItem1->hold_quantity);
        $this->assertSame('120.000', (string) $billItem1->effective_claimed_quantity);
        // Capped at Ordered = 100.000; Excess = 20.000
        $this->assertSame('100.000', (string) $billItem1->current_billable_quantity);
        $this->assertSame('20.000', (string) $billItem1->excess_quantity);
        $this->assertSame('100.000', (string) $billItem1->cumulative_quantity);
        $this->assertSame('0.000', (string) $billItem1->balance_quantity);
        $this->assertSame('cap_100', $billItem1->warning_level);

        // Basic A = 100 * 500 = 50,000.00; Excess unbilled = 20 * 500 = 10,000.00
        $this->assertSame('50000.00', (string) $raBill1->current_bill_amount);
        $this->assertSame('10000.00', (string) $raBill1->excess_unbilled_amount);
        $this->assertSame('4500.00', (string) $raBill1->cgst_amount);
        $this->assertSame('4500.00', (string) $raBill1->sgst_amount);
        $this->assertSame('59000.00', (string) $raBill1->gross_amount);

        // 4. Approve 1ST RA BILL (Tier 1 Site Manager -> Tier 2 Senior Manager)
        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.ra_bills.approve_site', [$workOrder, $raBill1]), ['notes' => 'Site verified'])
            ->assertRedirect();

        // Non-self-approval: if siteManagerA also had senior role, they still couldn't self-approve Tier 2
        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill1]))
            ->assertStatus(403);

        $this->actingAs($this->seniorManager)
            ->post(route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill1]), ['notes' => 'Approved for billing'])
            ->assertRedirect();

        $this->assertSame('approved', $raBill1->fresh()->status);
        $this->assertSame('billed', $firstMeasurement->fresh()->status);

        // 5. Propose & Approve Work Order Revision #1 increasing Ordered Qty from 100 to 150 M3
        $this->actingAs($this->officeStaffA)->post(route('work_orders.revisions.store', $workOrder), [
            'reason' => 'JSW Amendment #1 — Ordered quantity increased to 150 M3',
            'items' => [
                [
                    'id' => $woItem->id,
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Earthwork Excavation in Soil & Soft Rock',
                    'unit' => 'M3',
                    'ordered_quantity' => 150.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $rev1 = $workOrder->revisions()->where('revision_number', 1)->firstOrFail();
        $this->actingAs($this->seniorManager)
            ->post(route('work_orders.revisions.approve', [$workOrder, $rev1->id]))
            ->assertRedirect();

        // 6. Record a new Period 2 measurement of 15 M3 and generate 2ND RA BILL
        $this->actingAs($this->siteManagerA)->post(route('work_orders.measurements.store', $workOrder), [
            'work_order_item_id' => $woItem->id,
            'measured_on' => '2026-11-05',
            'location_chainage' => 'SMS-2 Grid B1-B2',
            'nos' => 1,
            'direct_quantity' => 15.000,
            'hold_quantity' => 0.000,
        ])->assertRedirect();

        $this->actingAs($this->officeStaffA)->post(route('work_orders.ra_bills.store', $workOrder), [
            'period_from' => '2026-11-01',
            'period_to' => '2026-11-30',
            'rounding_mode' => 'paisa',
        ])->assertRedirect();

        $raBill2 = RaBill::with('items')->where('work_order_id', $workOrder->id)->where('ra_number', 2)->firstOrFail();
        $this->assertSame('2ND RA BILL', $raBill2->ra_label);
        $this->assertSame(1, (int) $raBill2->work_order_revision);

        $billItem2 = $raBill2->items->firstOrFail();
        // Previous billed = 100.000
        // Carried-forward excess from RA #1 (20.000) + new measurement (15.000) = 35.000 claimed!
        // Since new Ordered = 150.000, remaining available = 50.000 -> all 35.000 is now unlocked & billable!
        $this->assertSame('150.000', (string) $billItem2->ordered_quantity);
        $this->assertSame('100.000', (string) $billItem2->previous_quantity);
        $this->assertSame('35.000', (string) $billItem2->claimed_quantity);
        $this->assertSame('35.000', (string) $billItem2->current_billable_quantity);
        $this->assertSame('0.000', (string) $billItem2->excess_quantity);
        $this->assertSame('135.000', (string) $billItem2->cumulative_quantity);
        $this->assertSame('15.000', (string) $billItem2->balance_quantity);
        $this->assertSame('90.00', (string) $billItem2->consumption_percent);
        $this->assertSame('warning_90', $billItem2->warning_level);
        // 35 * 500 = 17,500.00
        $this->assertSame('17500.00', (string) $raBill2->current_bill_amount);
    }

    public function test_benchmark_seven_lines_standard_rounding_paisa_and_rupee(): void
    {
        // Benchmark test with the 7 sample line items whose 2-decimal rounded amounts sum to A = 4,048,828.01:
        // CGST = SGST = round(4,048,828.01 * 0.09, 2) = 364,394.52
        // Gross = 4,048,828.01 + 364,394.52 + 364,394.52 = 4,777,617.05 (paisa) and 4,777,617 (rupee)
        $benchmarkLines = [
            ['sr_no' => '1.10', 'description' => 'Line 1 Excavation', 'qty' => 1.000, 'rate' => 589626.38],
            ['sr_no' => '1.20', 'description' => 'Line 2 Backfilling', 'qty' => 1.000, 'rate' => 412350.15],
            ['sr_no' => '1.30', 'description' => 'Line 3 PCC M15', 'qty' => 1.000, 'rate' => 628910.40],
            ['sr_no' => '1.40', 'description' => 'Line 4 RCC M25', 'qty' => 1.000, 'rate' => 945120.75],
            ['sr_no' => '1.50', 'description' => 'Line 5 Formwork', 'qty' => 1.000, 'rate' => 384210.11],
            ['sr_no' => '1.60', 'description' => 'Line 6 Reinforcement', 'qty' => 1.000, 'rate' => 712490.00],
            ['sr_no' => '1.70', 'description' => 'Line 7 Structural Steel', 'qty' => 1.000, 'rate' => 376120.22],
        ];

        $woItemsPayload = [];
        foreach ($benchmarkLines as $line) {
            $woItemsPayload[] = [
                'sr_no' => $line['sr_no'],
                'service_code' => '6003235',
                'sac_code' => '995419',
                'description' => $line['description'],
                'unit' => 'LS',
                'ordered_quantity' => $line['qty'],
                'rate' => $line['rate'],
            ];
        }

        $this->actingAs($this->officeStaffA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'client_id' => $this->client->id,
            'order_type' => 'client_issued',
            'po_number' => '4700226845',
            'title' => 'JSW 13th RA Bill Benchmark Order',
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'retention_percent' => 0.00,
            'items' => $woItemsPayload,
        ])->assertRedirect();

        $workOrder = WorkOrder::with('items')->latest('id')->firstOrFail();

        $claimedPayload = [];
        foreach ($workOrder->items as $woItem) {
            $claimedPayload[$woItem->id] = [
                'claimed_quantity' => 1.000,
                'hold_quantity' => 0.000,
            ];
        }

        // 1. Paisa mode
        $this->actingAs($this->officeStaffA)->post(route('work_orders.ra_bills.store', $workOrder), [
            'ra_label' => '13TH RA BILL',
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-31',
            'rounding_mode' => 'paisa',
            'items' => $claimedPayload,
        ])->assertRedirect();

        $raBill = RaBill::where('work_order_id', $workOrder->id)->firstOrFail();
        $this->assertSame('4048828.01', (string) $raBill->current_bill_amount);
        $this->assertSame('364394.52', (string) $raBill->cgst_amount);
        $this->assertSame('364394.52', (string) $raBill->sgst_amount);
        $this->assertSame('4777617.05', (string) $raBill->gross_amount);
        $this->assertSame('4777617.05', (string) $raBill->net_payable);

        // 2. Switch to Rupee rounding mode -> net_payable becomes 4,777,617.00
        $this->actingAs($this->officeStaffA)->put(route('work_orders.ra_bills.update', [$workOrder, $raBill]), [
            'rounding_mode' => 'rupee',
            'items' => $claimedPayload,
        ])->assertRedirect();

        $raBill->refresh();
        $this->assertSame('4777617.05', (string) $raBill->gross_amount);
        $this->assertSame('4777617.00', (string) $raBill->net_payable);
    }
}

