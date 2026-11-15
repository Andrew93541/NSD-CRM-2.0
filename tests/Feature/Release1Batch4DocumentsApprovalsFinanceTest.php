<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CrmNotification;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MeasurementEntry;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectMilestone;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderItem;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Release1Batch4DocumentsApprovalsFinanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $seniorManager;

    private User $siteManagerA;

    private User $siteManagerB;

    private User $supervisorA;

    private User $officeStaff;

    private Project $project;

    private Site $siteA;

    private Site $siteB;

    private Client $client;

    private WorkOrder $workOrderA;

    private WorkOrderItem $woItemA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Vanguard Civil Infra Pvt Ltd',
            'code' => 'VCI-01',
            'gstin' => '27AABCV1234F1Z5',
            'pan' => 'AABCV1234F',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        TenantContext::setCompanyId($this->company->id);

        $defaults = PermissionCatalog::defaultRolePermissions();
        $roles = [];
        foreach (['company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'] as $slug) {
            $roles[$slug] = Role::create([
                'company_id' => $this->company->id,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'permissions' => $defaults[$slug],
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $this->admin = $this->createCompanyUser('Admin User', 'admin@vanguard.test', $roles['company_admin'], false);
        $this->seniorManager = $this->createCompanyUser('Senior Manager', 'sm@vanguard.test', $roles['senior_manager'], false);
        $this->siteManagerA = $this->createCompanyUser('Site Manager A', 'sma@vanguard.test', $roles['site_manager'], true);
        $this->siteManagerB = $this->createCompanyUser('Site Manager B', 'smb@vanguard.test', $roles['site_manager'], true);
        $this->supervisorA = $this->createCompanyUser('Supervisor A', 'supa@vanguard.test', $roles['site_supervisor'], true);
        $this->officeStaff = $this->createCompanyUser('Office Staff', 'office@vanguard.test', $roles['office_staff'], false);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'code' => 'CL-JSW',
            'name' => 'JSW Infrastructure Ltd',
            'gstin' => '29AABCJ9999K1Z2',
            'is_active' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-VCI-01',
            'name' => 'Jetty Approach Road',
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-A',
            'name' => 'North Berth Package',
            'status' => 'active',
            'supervisor_id' => $this->supervisorA->id,
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-B',
            'name' => 'South Berth Package',
            'status' => 'active',
        ]);

        $this->siteManagerA->sites()->sync([$this->siteA->id]);
        $this->supervisorA->sites()->sync([$this->siteA->id]);
        $this->siteManagerB->sites()->sync([$this->siteB->id]);

        $this->workOrderA = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'client_id' => $this->client->id,
            'order_number' => 'WO-VCI-2026-0001',
            'po_number' => 'PO-JSW-8811',
            'title' => 'Rock Bund Excavation & Haulage',
            'order_type' => 'client_issued',
            'status' => 'approved',
            'tax_mode' => 'igst',
            'cgst_rate' => 0,
            'sgst_rate' => 0,
            'igst_rate' => 18.00,
            'retention_percent' => 5.00,
            'base_order_value' => 100000.00,
            'estimated_amount' => 100000.00,
            'created_by' => $this->admin->id,
        ]);

        $this->woItemA = WorkOrderItem::create([
            'company_id' => $this->company->id,
            'work_order_id' => $this->workOrderA->id,
            'line_no' => 1,
            'sr_no' => '1.01',
            'service_code' => 'SRV-EXC-01',
            'sac_code' => '995419',
            'description' => 'Hard Rock Excavation & Disposal',
            'unit' => 'Cum',
            'ordered_quantity' => 500.000,
            'rate' => 200.00,
            'line_amount' => 100000.00,
        ]);
    }

    private function createCompanyUser(string $name, string $email, Role $role, bool $restrictSites): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('StrongPass#2026'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => $restrictSites,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
            'restrict_to_assigned_sites' => $restrictSites,
        ]);

        return $user;
    }

    public function test_vault_storage_disk_abstraction_persists_and_downloads_private_documents(): void
    {
        Storage::fake('vault');
        Config::set('filesystems.vault_disk', 'vault');

        // 1. Project Document upload + download + delete on 'vault' disk
        $pdfFile = UploadedFile::fake()->create('structural_drawing.pdf', 120, 'application/pdf');
        $this->actingAs($this->siteManagerA)
            ->post(route('projects.documents.store', $this->project), [
                'title' => 'North Berth Foundation Drawing',
                'document_type' => 'drawing',
                'site_id' => $this->siteA->id,
                'document' => $pdfFile,
            ])
            ->assertRedirect();

        $projectDoc = ProjectDocument::firstOrFail();
        $this->assertSame('vault', $projectDoc->storage_disk);
        Storage::disk('vault')->assertExists($projectDoc->file_path);

        // Site Manager A can download; Site Manager B (restricted to Site B) gets 403
        $this->actingAs($this->siteManagerA)
            ->get(route('projects.documents.download', [$this->project, $projectDoc]))
            ->assertOk();

        $this->actingAs($this->siteManagerB)
            ->get(route('projects.documents.download', [$this->project, $projectDoc]))
            ->assertForbidden();

        // 2. Work Order Attachment on 'vault' disk
        $woAttachmentFile = UploadedFile::fake()->create('signed_amendment.pdf', 80, 'application/pdf');
        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.attachments.store', $this->workOrderA), [
                'attachment' => $woAttachmentFile,
            ])
            ->assertRedirect();

        $woAttachment = WorkOrderAttachment::firstOrFail();
        $this->assertSame('vault', $woAttachment->storage_disk);
        Storage::disk('vault')->assertExists($woAttachment->file_path);

        $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.attachments.download', [$this->workOrderA, $woAttachment]))
            ->assertOk();

        // 3. Worker Document on 'vault' disk
        $worker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-VCI-0001',
            'first_name' => 'Ramesh',
            'last_name' => 'Patil',
            'trade_designation' => 'Surveyor',
            'assigned_site_id' => $this->siteA->id,
            'pay_type' => 'monthly',
            'monthly_salary' => 28000,
            'daily_rate' => 933,
            'status' => 'active',
            'joining_date' => '2026-01-10',
        ]);

        $idScan = UploadedFile::fake()->create('id_proof.pdf', 90, 'application/pdf');
        $this->actingAs($this->siteManagerA)
            ->post(route('workforce.documents.store', $worker), [
                'documents' => [$idScan],
                'document_types' => ['aadhaar_identity'],
                'document_titles' => ['Masked Aadhaar Card'],
            ])
            ->assertRedirect();

        $workerDoc = WorkerDocument::where('worker_id', $worker->id)->firstOrFail();
        $this->assertSame('vault', $workerDoc->storage_disk);
        Storage::disk('vault')->assertExists($workerDoc->file_path);

        $this->actingAs($this->siteManagerA)
            ->get(route('workforce.documents.download', [$worker, $workerDoc->id]))
            ->assertOk();
    }

    public function test_supervisor_draft_measurement_requires_independent_verification_before_igst_ra_billing(): void
    {
        Storage::fake('vault');
        Config::set('filesystems.vault_disk', 'vault');

        // Supervisor A records a measurement -> saved as 'draft'
        $this->actingAs($this->supervisorA)
            ->post(route('work_orders.measurements.store', $this->workOrderA), [
                'work_order_item_id' => $this->woItemA->id,
                'measured_on' => '2026-10-10',
                'location_chainage' => 'CH 0+120',
                'nos' => 1,
                'length' => 20,
                'breadth' => 5,
                'depth_height' => 2,
                'remarks' => 'Chainage 120 bench cut',
            ])
            ->assertRedirect();

        $measurement = MeasurementEntry::where('work_order_id', $this->workOrderA->id)->firstOrFail();
        $this->assertSame('draft', $measurement->status);
        $this->assertEquals(200.000, (float) $measurement->computed_quantity);

        // Self-verification or unauthorized verification is forbidden
        $this->actingAs($this->supervisorA)
            ->post(route('work_orders.measurements.verify', [$this->workOrderA, $measurement->id]))
            ->assertForbidden();

        // Unverified draft measurement is NOT auto-claimed in RA Bill wizard
        $wizardBefore = $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.ra_bills.create', $this->workOrderA))
            ->assertOk();
        $wizardItemsBefore = $wizardBefore->viewData('wizardItems');
        $this->assertEquals(0.0, (float) $wizardItemsBefore[0]['claimed_quantity']);

        // Supervisor can edit unbilled draft measurement before verification
        $this->actingAs($this->supervisorA)
            ->put(route('work_orders.measurements.update', [$this->workOrderA, $measurement->id]), [
                'measured_on' => '2026-10-10',
                'location_chainage' => 'CH 0+125',
                'nos' => 1,
                'length' => 25,
                'breadth' => 4,
                'depth_height' => 2,
            ])
            ->assertRedirect();

        $measurement->refresh();
        $this->assertEquals(200.000, (float) $measurement->computed_quantity);
        $this->assertSame('CH 0+125', $measurement->location_chainage);

        // Site Manager A independently verifies the measurement
        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.measurements.verify', [$this->workOrderA, $measurement->id]))
            ->assertRedirect();

        $measurement->refresh();
        $this->assertSame('verified', $measurement->status);
        $this->assertSame($this->siteManagerA->id, (int) $measurement->verified_by);

        // Now RA Bill wizard picks up 200.000 verified quantity, and we create an Inter-State IGST RA Bill (18%)
        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.ra_bills.store', $this->workOrderA), [
                'ra_label' => 'RA-1',
                'period_from' => '2026-10-01',
                'period_to' => '2026-10-31',
                'invoice_date' => '2026-10-31',
                'tax_mode' => 'igst',
                'igst_rate' => 18.00,
                'rounding_mode' => 'paisa',
                'items' => [
                    $this->woItemA->id => [
                        'claimed_quantity' => 200.000,
                        'hold_quantity' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $raBill = RaBill::where('work_order_id', $this->workOrderA->id)->firstOrFail();
        // 200 Cum * ₹200 = ₹40,000 basic; IGST 18% = ₹7,200; Gross = ₹47,200; Retention 5% of ₹40,000 = ₹2,000 -> Net = ₹45,200
        $this->assertSame('igst', $raBill->tax_mode);
        $this->assertEquals(40000.00, (float) $raBill->current_bill_amount);
        $this->assertEquals(0.00, (float) $raBill->cgst_amount);
        $this->assertEquals(0.00, (float) $raBill->sgst_amount);
        $this->assertEquals(7200.00, (float) $raBill->igst_amount);
        $this->assertEquals(47200.00, (float) $raBill->gross_amount);
        $this->assertEquals(45200.00, (float) $raBill->net_payable);

        // Linked measurement is now locked from deletion
        $measurement->refresh();
        $this->assertSame($raBill->id, (int) $measurement->ra_bill_id);

        $this->actingAs($this->siteManagerA)
            ->delete(route('work_orders.measurements.destroy', [$this->workOrderA, $measurement->id]))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('measurement_entries', ['id' => $measurement->id]);

        // Global RA Bills register (/ra-bills) shows RA-1 to Site Manager A, hides it from Site Manager B
        $this->actingAs($this->siteManagerA)
            ->get(route('ra_bills.index'))
            ->assertOk()
            ->assertSee('RA-1')
            ->assertSee('IGST 18.0%');

        $this->actingAs($this->siteManagerB)
            ->get(route('ra_bills.index'))
            ->assertOk()
            ->assertDontSee('WO-VCI-2026-0001');

        // Export Tax Invoice PDF & XLSX onto 'vault' disk
        $this->actingAs($this->admin)
            ->get(route('work_orders.ra_bills.export_pdf', [$this->workOrderA, $raBill, 'combined_pdf']))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('work_orders.ra_bills.export_xlsx', [$this->workOrderA, $raBill]))
            ->assertOk();

        // Export Measurement Book PDF
        $mbResponse = $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.measurements.pdf', $this->workOrderA));
        $mbResponse->assertOk();
        $this->assertStringStartsWith('%PDF', $mbResponse->streamedContent());
    }

    public function test_printable_po_grn_worker_qr_id_payslip_pdfs_and_sanitized_bank_payout_csv(): void
    {
        $warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'name' => 'North Berth Store',
            'code' => 'WH-NB-01',
            'is_active' => true,
        ]);

        $cat = MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Aggregates',
            'code' => 'CAT-AGG',
        ]);

        $mat = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $cat->id,
            'name' => '20mm Crushed Aggregate',
            'code' => 'MAT-AGG-20',
            'unit_of_measure' => 'MT',
            'minimum_stock_level' => 20,
            'standard_unit_cost' => 950,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_id' => $this->company->id,
            'code' => 'VND-001',
            'name' => 'Konkan Quarries LLP',
            'vendor_type' => 'material_supplier',
            'gstin' => '27AABCK1111L1Z9',
            'is_active' => true,
        ]);

        // Create & approve PR -> PO -> GRN
        $this->actingAs($this->siteManagerA)->post(route('procurement.store_request'), [
            'site_id' => $this->siteA->id,
            'warehouse_id' => $warehouse->id,
            'urgency' => 'normal',
            'items' => [['material_id' => $mat->id, 'quantity' => 50]],
        ])->assertRedirect();

        $pr = PurchaseRequest::firstOrFail();
        $this->actingAs($this->seniorManager)->post(route('procurement.approve_request', $pr))->assertRedirect();

        $this->actingAs($this->admin)->post(route('procurement.store_po', $pr), [
            'vendor_id' => $vendor->id,
            'supplier_name' => $vendor->name,
            'unit_prices' => [$mat->id => 920.00],
        ])->assertRedirect();

        $po = PurchaseOrder::firstOrFail();

        // PO PDF authorized for Site Manager A, forbidden for Site Manager B
        $poPdf = $this->actingAs($this->siteManagerA)->get(route('procurement.orders.pdf', $po));
        $poPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $poPdf->streamedContent());

        $this->actingAs($this->siteManagerB)->get(route('procurement.orders.pdf', $po))->assertForbidden();

        // Receive goods -> GRN PDF
        $this->actingAs($this->siteManagerA)->post(route('procurement.confirm_receipt', $po), [
            'destination_warehouse_id' => $warehouse->id,
            'challan_or_invoice_no' => 'CH-9091',
            'received' => [$mat->id => 50],
        ])->assertRedirect();

        $grnTx = $po->transactions()->where('transaction_type', 'goods_receipt')->firstOrFail();
        $grnPdf = $this->actingAs($this->siteManagerA)->get(route('inventory.grn_pdf', $grnTx));
        $grnPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $grnPdf->streamedContent());

        $this->actingAs($this->siteManagerB)->get(route('inventory.grn_pdf', $grnTx))->assertForbidden();

        // Worker QR ID Card PDF + Payroll Payslip PDF + Bank Payout CSV with formula-injection protection
        $worker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-VCI-0099',
            'first_name' => 'Suresh',
            'last_name' => 'Kulkarni',
            'trade_designation' => 'Site Foreman',
            'assigned_site_id' => $this->siteA->id,
            'pay_type' => 'monthly',
            'monthly_salary' => 30000,
            'daily_rate' => 1000,
            'bank_account_name' => '=CMD|\' /C calc\'!A0',
            'bank_account_number' => '123456789012',
            'bank_ifsc' => 'HDFC0001234',
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ]);

        $idCardResponse = $this->actingAs($this->siteManagerA)->get(route('workforce.id_card_pdf', $worker));
        $idCardResponse->assertOk();
        $this->assertStringStartsWith('%PDF', $idCardResponse->streamedContent());

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteA->id,
            'period_code' => 'PAY-2026-10',
            'title' => 'October 2026 Site A Payroll',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'draft',
            'total_workers' => 1,
            'total_gross_amount' => 30000,
            'total_deductions' => 0,
            'total_net_amount' => 30000,
        ]);

        $entry = PayrollEntry::create([
            'company_id' => $this->company->id,
            'payroll_period_id' => $period->id,
            'worker_id' => $worker->id,
            'site_id' => $this->siteA->id,
            'pay_type' => 'monthly',
            'present_days' => 26,
            'effective_days' => 26,
            'worked_hours' => 208,
            'base_rate' => 30000,
            'base_pay' => 30000,
            'gross_pay' => 30000,
            'net_pay' => 30000,
            'payment_status' => 'pending',
        ]);

        $payslipPdf = $this->actingAs($this->siteManagerA)->get(route('payroll.payslip_pdf', [$period, $entry]));
        $payslipPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $payslipPdf->streamedContent());

        $payoutCsv = $this->actingAs($this->siteManagerA)->get(route('payroll.export_payout', $period));
        $payoutCsv->assertOk();
        $csvText = $payoutCsv->streamedContent();
        $this->assertStringContainsString("'=CMD|", $csvText);
        $this->assertStringContainsString('30000.00', $csvText);
    }

    public function test_worker_user_account_linking_and_bidirectional_deactivation_plus_milestone_lifecycle(): void
    {
        // Link supervisorA login to an employee worker record
        $this->actingAs($this->admin)->post(route('workforce.store'), [
            'worker_type' => 'employee',
            'user_id' => $this->supervisorA->id,
            'first_name' => 'Supervisor',
            'last_name' => 'Alpha',
            'phone' => '9898989811',
            'trade_designation' => 'Site Supervisor',
            'assigned_site_id' => $this->siteA->id,
            'pay_type' => 'monthly',
            'monthly_salary' => 32000,
            'daily_rate' => 1066,
            'status' => 'active',
            'joining_date' => '2026-02-01',
        ])->assertRedirect(route('workforce.index'));

        $linkedWorker = Worker::where('user_id', $this->supervisorA->id)->firstOrFail();
        $this->assertSame('active', $linkedWorker->status);

        // Deactivating the User account via toggleUserActive automatically marks linked Worker inactive
        $this->actingAs($this->admin)
            ->post(route('users.toggle_active', $this->supervisorA))
            ->assertRedirect();

        $linkedWorker->refresh();
        $this->assertSame('inactive', $linkedWorker->status);

        // Reactivate user and worker, then terminate Worker -> automatically deactivates linked User login
        $this->supervisorA->forceFill(['is_active' => true])->save();
        DB::table('company_user')
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->supervisorA->id)
            ->update(['status' => 'active']);
        $linkedWorker->update(['status' => 'active']);

        $this->actingAs($this->admin)->put(route('workforce.update', $linkedWorker), [
            'worker_type' => 'employee',
            'user_id' => $this->supervisorA->id,
            'first_name' => 'Supervisor',
            'last_name' => 'Alpha',
            'phone' => '9898989811',
            'trade_designation' => 'Site Supervisor',
            'assigned_site_id' => $this->siteA->id,
            'pay_type' => 'monthly',
            'monthly_salary' => 32000,
            'daily_rate' => 1066,
            'status' => 'terminated',
            'exit_date' => '2026-10-31',
            'joining_date' => '2026-02-01',
        ])->assertRedirect(route('workforce.index'));

        $this->supervisorA->refresh();
        $this->assertFalse((bool) $this->supervisorA->is_active);
        $pivotRow = DB::table('company_user')
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->supervisorA->id)
            ->first();
        $this->assertSame('inactive', $pivotRow->status);

        // Project Milestone create -> edit title/status -> delete
        $this->actingAs($this->admin)->post(route('projects.milestones.store', $this->project), [
            'title' => 'Subgrade Compaction Complete',
            'site_id' => $this->siteA->id,
            'due_date' => '2026-12-15',
            'percent_complete' => 25,
            'status' => 'in_progress',
        ])->assertRedirect();

        $milestone = ProjectMilestone::where('project_id', $this->project->id)->firstOrFail();

        $this->actingAs($this->admin)->put(route('projects.milestones.update', [$this->project, $milestone]), [
            'title' => 'Subgrade & GSB Layer Complete',
            'status' => 'completed',
            'percent_complete' => 100,
        ])->assertRedirect();

        $milestone->refresh();
        $this->assertSame('Subgrade & GSB Layer Complete', $milestone->title);
        $this->assertSame(100, (int) $milestone->percent_complete);

        $this->actingAs($this->admin)
            ->delete(route('projects.milestones.destroy', [$this->project, $milestone]))
            ->assertRedirect();
        $this->assertDatabaseMissing('project_milestones', ['id' => $milestone->id]);
    }

    public function test_unified_approvals_center_and_deduplicated_notifications_respect_site_and_maker_checker_rules(): void
    {
        // Supervisor A records a draft measurement on Site A
        $this->actingAs($this->supervisorA)
            ->post(route('work_orders.measurements.store', $this->workOrderA), [
                'work_order_item_id' => $this->woItemA->id,
                'measured_on' => '2026-10-12',
                'nos' => 1,
                'direct_quantity' => 45.5,
            ])
            ->assertRedirect();

        // Site Manager A receives unread notification; Site Manager B (Site B) and Supervisor A (actor) do NOT
        $this->assertSame(
            1,
            CrmNotification::where('user_id', $this->siteManagerA->id)->whereNull('read_at')->count()
        );
        $this->assertSame(
            0,
            CrmNotification::where('user_id', $this->siteManagerB->id)->whereNull('read_at')->count()
        );
        $this->assertSame(
            0,
            CrmNotification::where('user_id', $this->supervisorA->id)->whereNull('read_at')->count()
        );

        // Recording another draft measurement on the same Work Order updates existing unread alert instead of spamming duplicates
        $this->actingAs($this->supervisorA)
            ->post(route('work_orders.measurements.store', $this->workOrderA), [
                'work_order_item_id' => $this->woItemA->id,
                'measured_on' => '2026-10-13',
                'nos' => 1,
                'direct_quantity' => 12.0,
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            CrmNotification::where('user_id', $this->siteManagerA->id)
                ->where('type', 'measurement.verification_pending')
                ->whereNull('read_at')
                ->count()
        );

        // Approval Center shows the 2 pending measurements to Site Manager A, 0 to Site Manager B, and 0 to Supervisor A
        $respSmA = $this->actingAs($this->siteManagerA)->get(route('approvals.index'));
        $respSmA->assertOk()->assertSee('WO-VCI-2026-0001');
        $this->assertSame(2, $respSmA->viewData('counts')['measurements']);

        $respSmB = $this->actingAs($this->siteManagerB)->get(route('approvals.index'));
        $respSmB->assertOk();
        $this->assertSame(0, $respSmB->viewData('counts')['all']);

        $respSup = $this->actingAs($this->supervisorA)->get(route('approvals.index'));
        $respSup->assertOk();
        $this->assertSame(0, $respSup->viewData('counts')['measurements']);
    }
}
