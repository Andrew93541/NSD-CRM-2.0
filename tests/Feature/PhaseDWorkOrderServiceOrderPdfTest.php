<?php

namespace Tests\Feature;

use App\Models\BillExport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseDWorkOrderServiceOrderPdfTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Project $project;
    private Site $siteA;
    private Site $siteB;
    private Client $client;
    private User $siteManagerA;
    private User $siteManagerB;

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

        $role = Role::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'site_manager'],
            ['name' => 'Site Manager', 'permissions' => ['work_orders.view', 'work_orders.create', 'work_orders.approve_site']]
        );

        $this->siteManagerA = User::factory()->create(['name' => 'Site Manager A', 'is_active' => true]);
        $this->siteManagerA->companies()->attach($this->company->id, ['role_id' => $role->id]);
        $this->siteA->assignedUsers()->attach($this->siteManagerA->id);

        $this->siteManagerB = User::factory()->create(['name' => 'Site Manager B', 'is_active' => true]);
        $this->siteManagerB->companies()->attach($this->company->id, ['role_id' => $role->id]);
        $this->siteB->assignedUsers()->attach($this->siteManagerB->id);
    }

    public function test_jsw_service_order_print_and_pdf_export_for_subcontractors_and_received_orders(): void
    {
        Storage::fake('local');

        $this->actingAs($this->siteManagerA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'client_id' => $this->client->id,
            'order_type' => 'internal',
            'po_number' => '4700226845',
            'po_date' => '2026-10-15',
            'job_location' => 'JSW Steel Ltd, Dolvi Works - SMS-2',
            'user_department_person' => 'Civil Projects / Mr. S. Kulkarni',
            'effective_date' => '2026-10-15',
            'completion_date' => '2027-03-31',
            'contractor_name' => 'Shree Sai Earthmovers & Infra',
            'contractor_gst' => '27AABCS1429B1Z1',
            'contractor_contact' => 'Rajesh Patil (+91 9820112233)',
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'retention_percent' => 5.00,
            'title' => 'Subcontract Service Order for SMS-2 Excavation & PCC',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Hard Rock Excavation & Disposal',
                    'specification' => 'Controlled blasting / rock breaker up to 3m depth',
                    'unit' => 'M3',
                    'ordered_quantity' => 200.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::firstOrFail();

        // 1. Verify Print View renders JSW Service Order fields, sr_no '1.10', specification, and Indian currency in words
        $printRes = $this->actingAs($this->siteManagerA)->get(route('work_orders.print', $workOrder));
        $printRes->assertStatus(200);
        $printRes->assertSee('4700226845');
        $printRes->assertSee('Shree Sai Earthmovers');
        $printRes->assertSee('1.10');
        $printRes->assertSee('6003235');
        $printRes->assertSee('Controlled blasting / rock breaker up to 3m depth');
        // Base = 100,000 + CGST 9,000 + SGST 9,000 = 118,000 -> Rupees One Lakh Eighteen Thousand Only
        $printRes->assertSee('Rupees One Lakh Eighteen Thousand Only');

        // 2. Unassigned Site Manager B cannot export PDF (403)
        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.export_pdf', $workOrder))
            ->assertStatus(403);

        // 3. Assigned Site Manager A exports JSW Service Order PDF
        $pdfRes = $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.export_pdf', $workOrder));
        $pdfRes->assertStatus(200);

        $export = BillExport::where('work_order_id', $workOrder->id)
            ->where('export_type', 'work_order_pdf')
            ->firstOrFail();

        Storage::disk('local')->assertExists($export->file_path);
        $rawPdf = Storage::disk('local')->get($export->file_path);
        $this->assertStringStartsWith('%PDF-', $rawPdf);
    }
}

