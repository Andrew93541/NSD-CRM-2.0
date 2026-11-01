<?php

namespace Tests\Feature;

use App\Models\BillExport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\IndianCurrencyWords;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PhaseCRaBillExportsAndInvoiceSequenceTest extends TestCase
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
            'registered_address' => 'Plot 14, MIDC Industrial Area, Pen-Raigad, Maharashtra - 402107',
            'gstin' => '27AABCN1234F1Z5',
            'pan' => 'AABCN1234F',
            'vendor_code' => '100984',
            'bank_name' => 'State Bank of India',
            'bank_account_no' => '30998877665',
            'bank_ifsc' => 'SBIN0004521',
            'bank_branch' => 'Pen Raigad Branch',
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
            'address' => 'Geetapuram, Dolvi, Taluka Pen, Dist. Raigad - 402107',
            'gstin' => '27AAACJ4323N1Z5',
            'pan' => 'AAACJ4323N',
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

    public function test_indian_currency_words_and_indian_number_formatting(): void
    {
        $this->assertSame(
            'Rupees Forty Seven Lakh Seventy Seven Thousand Six Hundred Seventeen and Five Paise Only',
            IndianCurrencyWords::toWords(4777617.05)
        );

        $this->assertSame(
            'Rupees Forty Seven Lakh Seventy Seven Thousand Six Hundred Seventeen Only',
            IndianCurrencyWords::toWords(4777617.00)
        );

        $this->assertSame(
            'Rupees One Crore Twenty Five Lakh and Fifty Paise Only',
            IndianCurrencyWords::toWords(12500000.50)
        );

        $this->assertSame('47,77,617.05', IndianCurrencyWords::formatIndianNumber(4777617.05, 2));
        $this->assertSame('40,48,828.01', IndianCurrencyWords::formatIndianNumber(4048828.01, 2));
    }

    public function test_locked_fy_invoice_sequence_pdf_exports_and_live_formula_xlsx_benchmark(): void
    {
        Storage::fake('local');

        // Create the 7-line JSW 13th RA Bill benchmark order
        $benchmarkLines = [
            ['sr_no' => '1.10', 'description' => 'Line 1 Excavation', 'qty' => 2.000, 'rate' => 589626.38],
            ['sr_no' => '1.20', 'description' => 'Line 2 Backfilling', 'qty' => 2.000, 'rate' => 412350.15],
            ['sr_no' => '1.30', 'description' => 'Line 3 PCC M15', 'qty' => 2.000, 'rate' => 628910.40],
            ['sr_no' => '1.40', 'description' => 'Line 4 RCC M25', 'qty' => 2.000, 'rate' => 945120.75],
            ['sr_no' => '1.50', 'description' => 'Line 5 Formwork', 'qty' => 2.000, 'rate' => 384210.11],
            ['sr_no' => '1.60', 'description' => 'Line 6 Reinforcement', 'qty' => 2.000, 'rate' => 712490.00],
            ['sr_no' => '1.70', 'description' => 'Line 7 Structural Steel', 'qty' => 2.000, 'rate' => 376120.22],
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
            'po_date' => '2026-04-10',
            'job_location' => 'JSW Steel Ltd, Dolvi Works - SMS-2',
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

        // Create RA Bill #1 (labeled 13TH RA BILL as in sample) in Oct 2026 (FY 26-27)
        $this->actingAs($this->officeStaffA)->post(route('work_orders.ra_bills.store', $workOrder), [
            'ra_label' => '13TH RA BILL',
            'invoice_date' => '2026-10-25',
            'period_from' => '2026-10-01',
            'period_to' => '2026-10-25',
            'rounding_mode' => 'paisa',
            'items' => $claimedPayload,
        ])->assertRedirect();

        $raBill1 = RaBill::where('work_order_id', $workOrder->id)->firstOrFail();
        $this->assertNull($raBill1->invoice_number);

        // Tier 1 & Tier 2 Approval assigns locked invoice number CIVIL/21/26-27
        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.ra_bills.approve_site', [$workOrder, $raBill1]))
            ->assertRedirect();

        $this->actingAs($this->seniorManager)
            ->post(route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill1]))
            ->assertRedirect();

        $raBill1->refresh();
        $this->assertSame('CIVIL/21/26-27', $raBill1->invoice_number);

        // Unassigned Site Manager B cannot export PDF or XLSX (403)
        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.ra_bills.export_pdf', [$workOrder, $raBill1, 'tax_invoice_pdf']))
            ->assertStatus(403);

        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.ra_bills.export_xlsx', [$workOrder, $raBill1]))
            ->assertStatus(403);

        // Export Tax Invoice PDF, Abstract PDF, Combined PDF
        foreach (['tax_invoice_pdf', 'abstract_pdf', 'combined_pdf'] as $pdfType) {
            $pdfRes = $this->actingAs($this->officeStaffA)
                ->get(route('work_orders.ra_bills.export_pdf', [$workOrder, $raBill1, $pdfType]));
            $pdfRes->assertStatus(200);
        }

        // Export Live-Formula Excel (.xlsx)
        $xlsxRes = $this->actingAs($this->officeStaffA)
            ->get(route('work_orders.ra_bills.export_xlsx', [$workOrder, $raBill1]));
        $xlsxRes->assertStatus(200);

        // Re-exporting does not change the invoice number
        $this->assertSame('CIVIL/21/26-27', $raBill1->fresh()->invoice_number);

        // Verify 4 BillExport audit records exist and files are stored on private 'local' disk
        $exports = BillExport::where('ra_bill_id', $raBill1->id)->get();
        $this->assertCount(4, $exports);
        foreach ($exports as $exp) {
            Storage::disk('local')->assertExists($exp->file_path);
        }

        // Inspect the generated .xlsx file using PhpSpreadsheet to verify sheets, text sr_no '1.10', and live formulas
        $xlsxExport = BillExport::where('ra_bill_id', $raBill1->id)
            ->where('export_type', 'ra_bill_xlsx')
            ->firstOrFail();

        $fullXlsxPath = Storage::disk('local')->path($xlsxExport->file_path);
        $workbook = IOFactory::load($fullXlsxPath);

        $this->assertSame(['INV', 'ABSTRACT'], $workbook->getSheetNames());

        $abstractSheet = $workbook->getSheetByName('ABSTRACT');
        $invSheet = $workbook->getSheetByName('INV');

        // Verify '1.10' is preserved as explicit string '1.10' on ABSTRACT!A6
        $this->assertSame(DataType::TYPE_STRING, $abstractSheet->getCell('A6')->getDataType());
        $this->assertSame('1.10', (string) $abstractSheet->getCell('A6')->getValue());

        // Verify live formulas on ABSTRACT sheet (7 items on rows 6..12, totals on row 13)
        $this->assertSame('=H6+I6', $abstractSheet->getCell('J6')->getValue());
        $this->assertSame('=ROUND(I6*G6,2)', $abstractSheet->getCell('L6')->getValue());
        $this->assertSame('=SUM(L6:L12)', $abstractSheet->getCell('L13')->getValue());
        $this->assertSame(4048828.01, round((float) $abstractSheet->getCell('L13')->getCalculatedValue(), 2));

        // Verify live formulas and benchmark numbers on INV sheet
        $this->assertSame('CIVIL/21/26-27', (string) $invSheet->getCell('F6')->getValue());
        $this->assertSame('=ABSTRACT!L13', $invSheet->getCell('G14')->getValue());
        $this->assertSame(4048828.01, round((float) $invSheet->getCell('G14')->getCalculatedValue(), 2));
        $this->assertSame(364394.52, round((float) $invSheet->getCell('G15')->getCalculatedValue(), 2));
        $this->assertSame(364394.52, round((float) $invSheet->getCell('G16')->getCalculatedValue(), 2));
        $this->assertSame(4777617.05, round((float) $invSheet->getCell('G17')->getCalculatedValue(), 2));
        $this->assertSame(
            'Rupees Forty Seven Lakh Seventy Seven Thousand Six Hundred Seventeen and Five Paise Only',
            (string) $invSheet->getCell('B21')->getValue()
        );

        // Create and approve next RA Bill in Nov 2026 -> receives CIVIL/22/26-27
        $this->actingAs($this->officeStaffA)->post(route('work_orders.ra_bills.store', $workOrder), [
            'ra_label' => '14TH RA BILL',
            'invoice_date' => '2026-11-25',
            'period_from' => '2026-11-01',
            'period_to' => '2026-11-25',
            'rounding_mode' => 'paisa',
            'items' => $claimedPayload,
        ])->assertRedirect();

        $raBill2 = RaBill::where('work_order_id', $workOrder->id)->where('ra_number', 2)->firstOrFail();

        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.ra_bills.approve_site', [$workOrder, $raBill2]))
            ->assertRedirect();

        $this->actingAs($this->seniorManager)
            ->post(route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill2]))
            ->assertRedirect();

        $this->assertSame('CIVIL/22/26-27', $raBill2->fresh()->invoice_number);
    }
}

