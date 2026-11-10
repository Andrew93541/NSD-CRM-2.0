<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CrmNotification;
use App\Models\DailyAttendance;
use App\Models\MeasurementEntry;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\WorkOrderUpload;
use App\Services\Operations\JswWorkOrderPdfReaderService;
use App\Services\Operations\RaBillingService;
use App\Services\Operations\WorkOrderUploadService;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseGHWorkOrderUploadAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminA;
    protected User $seniorManagerA;
    protected User $siteManagerA;
    protected User $officeStaffA;
    protected User $supervisorA;
    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'Aimax Civil Infra Pvt Ltd',
            'code' => 'AIMAX',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->companyB = Company::create([
            'name' => 'Other Infra Ltd',
            'code' => 'OTHER',
            'status' => 'active',
            'is_active' => true,
        ]);

        $rolesA = [];
        $rolesB = [];
        foreach (['company_admin', 'senior_manager', 'site_manager', 'office_staff', 'site_supervisor'] as $slug) {
            $perms = $slug === 'site_supervisor' ? ['work_orders.view'] : ['*'];
            $rolesA[$slug] = Role::withoutGlobalScopes()->create([
                'company_id' => $this->companyA->id,
                'slug' => $slug,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'permissions' => $perms,
            ]);
            $rolesB[$slug] = Role::withoutGlobalScopes()->create([
                'company_id' => $this->companyB->id,
                'slug' => $slug,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'permissions' => $perms,
            ]);
        }

        $this->adminA = $this->createUserWithRole('Admin A', 'admin-a@aimax.test', $this->companyA, $rolesA['company_admin']);
        $this->seniorManagerA = $this->createUserWithRole('Senior Manager A', 'senior-a@aimax.test', $this->companyA, $rolesA['senior_manager']);
        $this->siteManagerA = $this->createUserWithRole('Site Manager A', 'sitemgr-a@aimax.test', $this->companyA, $rolesA['site_manager']);
        $this->officeStaffA = $this->createUserWithRole('Office Staff A', 'office-a@aimax.test', $this->companyA, $rolesA['office_staff']);
        $this->supervisorA = $this->createUserWithRole('Supervisor A', 'sup-a@aimax.test', $this->companyA, $rolesA['site_supervisor']);
        $this->adminB = $this->createUserWithRole('Admin B', 'admin-b@other.test', $this->companyB, $rolesB['company_admin']);
    }

    protected function createUserWithRole(string $name, string $email, Company $company, Role $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('Secret#12345'),
            'is_active' => true,
            'two_factor_enabled' => false,
        ]);

        $user->companies()->attach($company->id, [
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_both_jsw_sample_pdfs_parse_to_exact_reference_values_and_strip_page_noise(): void
    {
        $reader = app(JswWorkOrderPdfReaderService::class);

        // 1. PO 4700162257: 16 lines, base 6,338,917.50, CGST 570,503.00, SGST 570,503.00, total 7,479,923.50
        $res162257 = $reader->parsePdfFile(base_path('tests/Fixtures/jsw_po_4700162257.pdf'), '4700162257.pdf');
        $this->assertFalse($res162257['is_scanned_or_unreadable']);
        $this->assertTrue($res162257['is_draft_po']);
        $this->assertEmpty($res162257['validation_errors']);

        $payload1 = $res162257['payload'];
        $this->assertSame('4700162257', $payload1['header']['po_number']);
        $this->assertSame('Concrete Road Flooring EV Bus Parking and Civil foundations at Jetty Parking', $payload1['header']['subject']);
        $this->assertSame('Deepak Krishna', $payload1['header']['user_department_person']);
        $this->assertSame('Gyanendra Patra', $payload1['header']['buyer_name']);
        $this->assertSame('XXXXXXXXXXX0027', $payload1['vendor']['bank_account_masked']);
        $this->assertStringNotContainsString('014130110000027', json_encode($payload1));

        $this->assertCount(16, $payload1['items']);
        $this->assertSame(6338917.50, $payload1['totals']['base_value']);
        $this->assertSame(570503.00, $payload1['totals']['cgst_amount']);
        $this->assertSame(570503.00, $payload1['totals']['sgst_amount']);
        $this->assertSame(7479923.50, $payload1['totals']['total_po_value']);

        // Verify Sr. No. 1.1 and 1.10 stay distinct strings
        $srNos = array_column($payload1['items'], 'sr_no');
        $this->assertContains('1.1', $srNos);
        $this->assertContains('1.10', $srNos);
        $this->assertContains('1.16', $srNos);

        // Verify page headers/footers do not leak into descriptions or specification text, and multi-page specs join
        foreach ($payload1['items'] as $item) {
            $this->assertStringNotContainsStringIgnoringCase('Thanking You', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('Thanking You', $item['specification_text']);
            $this->assertStringNotContainsStringIgnoringCase('AUTHORISED SIGNATORY', $item['specification_text']);
            $this->assertStringNotContainsStringIgnoringCase('Plant GST No', $item['specification_text']);
        }
        // Item 1.1 starts on Page 1 and finishes on Page 2 ("STATUTORY OBLIGATION BY;VENDOR")
        $this->assertStringContainsString('STATUTORY OBLIGATION BY;VENDOR', $payload1['items'][0]['specification_text']);

        // 2. PO 4700157667: 5 lines, base 1,788,700.00, CGST 160,983.00, SGST 160,983.00, total 2,110,666.00
        $res157667 = $reader->parsePdfFile(base_path('tests/Fixtures/jsw_po_4700157667.pdf'), '4700157667.pdf');
        $this->assertFalse($res157667['is_scanned_or_unreadable']);
        $this->assertTrue($res157667['is_draft_po']);
        $this->assertEmpty($res157667['validation_errors']);

        $payload2 = $res157667['payload'];
        $this->assertSame('4700157667', $payload2['header']['po_number']);
        $this->assertSame('Civil work coil yard', $payload2['header']['subject']);
        $this->assertCount(5, $payload2['items']);
        $this->assertSame(1788700.00, $payload2['totals']['base_value']);
        $this->assertSame(160983.00, $payload2['totals']['cgst_amount']);
        $this->assertSame(160983.00, $payload2['totals']['sgst_amount']);
        $this->assertSame(2110666.00, $payload2['totals']['total_po_value']);
        $this->assertStringContainsString('TRANSPORTATION UP TO:1 KM;RATE TYPE:M3', $payload2['items'][3]['specification_text']);
    }

    public function test_upload_review_mismatch_blocks_confirm_and_confirm_creates_work_order_and_setup_pending_site_atomically(): void
    {
        $pdfContent = file_get_contents(base_path('tests/Fixtures/jsw_po_4700157667.pdf'));
        $uploadedFile = UploadedFile::fake()->createWithContent('4700157667.pdf', $pdfContent);

        // Office staff uploads PO 4700157667
        $response = $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('work_orders.uploads.store'), [
                'pdf_file' => $uploadedFile,
            ]);

        $upload = WorkOrderUpload::firstOrFail();
        $response->assertRedirect(route('work_orders.uploads.review', $upload));

        // Edit quantity of line 1.1 on review screen so totals no longer match (300 -> 310 while base_value stays 1,788,700)
        $payload = $upload->activePayload();
        $payload['items'][0]['ordered_qty'] = 310;
        $payload['items'][0]['line_total'] = 310 * 1254; // 388,740

        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->put(route('work_orders.uploads.update_review', $upload), [
                'header' => $payload['header'],
                'items' => $payload['items'],
                'totals' => $payload['totals'],
                'is_draft_po' => 1,
            ])
            ->assertRedirect(route('work_orders.uploads.review', $upload));

        $upload->refresh();
        $this->assertNotEmpty($upload->validation_errors);
        $this->assertArrayHasKey('base_value', $upload->validation_errors);

        // Confirm without override_reason MUST be blocked and create 0 work orders and 0 sites
        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->from(route('work_orders.uploads.review', $upload))
            ->post(route('work_orders.uploads.confirm', $upload), [])
            ->assertRedirect(route('work_orders.uploads.review', $upload))
            ->assertSessionHasErrors('validation_mismatch');

        $this->assertSame(0, WorkOrder::count());
        $this->assertSame(0, Site::count());

        // Restore line 1.1 to 300 so validation passes
        $payload['items'][0]['ordered_qty'] = 300;
        $payload['items'][0]['line_total'] = 376200.00;

        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->put(route('work_orders.uploads.update_review', $upload), [
                'header' => $payload['header'],
                'items' => $payload['items'],
                'totals' => $payload['totals'],
                'is_draft_po' => 1,
            ]);

        $upload->refresh();
        $this->assertEmpty($upload->validation_errors);

        // Confirm creates 1 Work Order, 5 lines, and 1 Site in setup_pending
        $confirmResponse = $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('work_orders.uploads.confirm', $upload), [
                'is_draft_po' => 1,
            ]);

        $this->assertSame(1, WorkOrder::count());
        $this->assertSame(1, Site::count());

        $wo = WorkOrder::with(['items', 'site', 'revisions'])->firstOrFail();
        $confirmResponse->assertRedirect(route('work_orders.show', $wo));

        $this->assertSame('4700157667', $wo->po_number);
        $this->assertCount(5, $wo->items);
        $this->assertSame(1788700.00, (float) $wo->base_order_value);
        $this->assertTrue($wo->isDraftPo());
        $this->assertSame(WorkOrder::LIFECYCLE_DRAFT_PO, $wo->effectiveLifecycleStatus());

        $site = $wo->site;
        $this->assertSame('Civil work coil yard', $site->name);
        $this->assertSame('PO-4700157667', $site->code);
        $this->assertSame('setup_pending', $site->status);

        // Managers were notified that a new site needs setup
        $this->assertTrue(
            CrmNotification::withoutGlobalScopes()
                ->where('company_id', $this->companyA->id)
                ->where('type', 'site.setup_pending')
                ->exists()
        );

        // Audit logs recorded upload, review edit, and confirm
        $this->assertTrue(AuditLog::where('action', 'work_order_upload.uploaded')->exists());
        $this->assertTrue(AuditLog::where('action', 'work_order_upload.review_edited')->exists());
        $this->assertTrue(AuditLog::where('action', 'work_order_upload.confirmed')->exists());
    }

    public function test_duplicate_po_upload_creates_revision_with_no_second_site_and_draft_po_blocks_billing_until_final_revision(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $pdfContent = file_get_contents(base_path('tests/Fixtures/jsw_po_4700157667.pdf'));
        $uploadService = app(WorkOrderUploadService::class);

        // 1. First upload & confirm of PO 4700157667 as Draft PO
        $upload1 = $uploadService->uploadAndExtract(
            $this->companyA->id,
            UploadedFile::fake()->createWithContent('4700157667_draft.pdf', $pdfContent),
            $this->officeStaffA,
            true
        );
        $result1 = $uploadService->confirmUpload($upload1, $this->officeStaffA);
        $wo = $result1['work_order'];
        $site = $result1['site'];

        $this->assertFalse($result1['is_revision']);
        $this->assertTrue($wo->isDraftPo());
        $this->assertSame(1, Site::count());

        // 2. Site setup pending -> active when GPS, geofence & site manager are set
        $site->update([
            'latitude' => 18.6969000,
            'longitude' => 73.0355000,
            'geofence_radius_m' => 250,
            'site_manager_id' => $this->siteManagerA->id,
            'supervisor_id' => $this->supervisorA->id,
        ]);
        $site->refresh();
        $this->assertSame('active', $site->status);

        // 3. Draft PO can record measurements, but CANNOT be billed
        $line11 = $wo->items()->where('sr_no', '1.1')->firstOrFail();
        $raBillingService = app(RaBillingService::class);

        $measurement = $raBillingService->recordMeasurement($wo, [
            'work_order_item_id' => $line11->id,
            'site_id' => $site->id,
            'measured_on' => now()->toDateString(),
            'direct_quantity' => 120,
        ], $this->supervisorA);
        $this->assertSame(120.0, (float) $measurement->computed_quantity);

        $this->expectException(ValidationException::class);
        $raBillingService->createOrRecalculateRaBill($wo, [
            'period_from' => '2026-04-10',
            'period_to' => '2026-04-25',
            'items' => [
                $line11->id => ['claimed_quantity' => 120, 'hold_quantity' => 0],
            ],
        ], $this->officeStaffA);
    }

    public function test_section_13_full_walkthrough_ra_bills_1_to_4_and_duplicate_po_revision_unlock(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $pdfContent = file_get_contents(base_path('tests/Fixtures/jsw_po_4700157667.pdf'));
        $uploadService = app(WorkOrderUploadService::class);
        $raBillingService = app(RaBillingService::class);

        // Upload & confirm PO 4700157667 as Confirmed (non-draft)
        $upload1 = $uploadService->uploadAndExtract(
            $this->companyA->id,
            UploadedFile::fake()->createWithContent('4700157667.pdf', $pdfContent),
            $this->officeStaffA,
            false
        );
        $res1 = $uploadService->confirmUpload($upload1, $this->officeStaffA, null, null, false);
        $wo = $res1['work_order'];
        $site = $res1['site'];

        $this->assertFalse($wo->isDraftPo());
        $this->assertSame(WorkOrder::LIFECYCLE_CONFIRMED, $wo->effectiveLifecycleStatus());

        $itemsBySr = $wo->items()->get()->keyBy('sr_no');

        // Enter measurement -> moves lifecycle to in_progress
        $raBillingService->recordMeasurement($wo, [
            'work_order_item_id' => $itemsBySr['1.1']->id,
            'site_id' => $site->id,
            'direct_quantity' => 120,
        ], $this->supervisorA);
        $wo->refresh();
        $this->assertSame(WorkOrder::LIFECYCLE_IN_PROGRESS, $wo->effectiveLifecycleStatus());

        // Site with measurements can NEVER be deleted
        $this->assertFalse($site->canBeDeleted());
        $this->actingAs($this->adminA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->delete(route('sites.destroy', $site))
            ->assertSessionHasErrors('site');
        $this->assertDatabaseHas('sites', ['id' => $site->id]);

        // === Section 13.2: RA Bill 1 (Normal bill: 1.1=120, 1.2=60, 1.4=4000) ===
        $bill1 = $raBillingService->createOrRecalculateRaBill($wo, [
            'period_from' => '2026-04-10',
            'period_to' => '2026-04-30',
            'items' => [
                $itemsBySr['1.1']->id => ['claimed_quantity' => 120, 'hold_quantity' => 0],
                $itemsBySr['1.2']->id => ['claimed_quantity' => 60, 'hold_quantity' => 0],
                $itemsBySr['1.4']->id => ['claimed_quantity' => 4000, 'hold_quantity' => 0],
            ],
        ], $this->officeStaffA);
        $bill1->update(['status' => 'approved']);

        $this->assertSame(665480.00, (float) $bill1->current_bill_amount);
        $this->assertSame(59893.20, (float) $bill1->cgst_amount);
        $this->assertSame(59893.20, (float) $bill1->sgst_amount);
        $this->assertSame(785266.40, (float) $bill1->gross_amount);

        // === Section 13.3: RA Bill 2 (Work order cap: 1.1=220, 1.3=10, 1.4=5500) ===
        $bill2 = $raBillingService->createOrRecalculateRaBill($wo, [
            'period_from' => '2026-05-01',
            'period_to' => '2026-05-31',
            'items' => [
                $itemsBySr['1.1']->id => ['claimed_quantity' => 220, 'hold_quantity' => 0],
                $itemsBySr['1.3']->id => ['claimed_quantity' => 10, 'hold_quantity' => 0],
                $itemsBySr['1.4']->id => ['claimed_quantity' => 5500, 'hold_quantity' => 0],
            ],
        ], $this->officeStaffA);
        $bill2->update(['status' => 'approved']);

        $bill2Items = $bill2->items()->get()->keyBy('sr_no');
        $this->assertSame(180.000, (float) $bill2Items['1.1']->current_billable_quantity);
        $this->assertSame(40.000, (float) $bill2Items['1.1']->excess_quantity);
        $this->assertSame(5000.000, (float) $bill2Items['1.4']->current_billable_quantity);
        $this->assertSame(500.000, (float) $bill2Items['1.4']->excess_quantity);
        $this->assertSame(903720.00, (float) $bill2->current_bill_amount);
        $this->assertSame(81334.80, (float) $bill2->cgst_amount);
        $this->assertSame(81334.80, (float) $bill2->sgst_amount);
        $this->assertSame(1066389.60, (float) $bill2->gross_amount);

        // === Section 13.4: RA Bill 3 (Hold quantity: 1.2=100 with hold=10) ===
        $bill3 = $raBillingService->createOrRecalculateRaBill($wo, [
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'items' => [
                $itemsBySr['1.2']->id => ['claimed_quantity' => 100, 'hold_quantity' => 10],
            ],
        ], $this->officeStaffA);
        $bill3->update(['status' => 'approved']);

        $bill3Item12 = $bill3->items()->where('sr_no', '1.2')->firstOrFail();
        $this->assertSame(90.000, (float) $bill3Item12->current_billable_quantity);
        $this->assertSame(0.000, (float) $bill3Item12->excess_quantity);
        $this->assertSame(40500.00, (float) $bill3->current_bill_amount);
        $this->assertSame(3645.00, (float) $bill3->cgst_amount);
        $this->assertSame(3645.00, (float) $bill3->sgst_amount);
        $this->assertSame(47790.00, (float) $bill3->gross_amount);

        // === Section 13.5 & 13.13: Upload same PO number again as Revision 1 raising line 1.1 to 350 M3 ===
        $upload2 = $uploadService->uploadAndExtract(
            $this->companyA->id,
            UploadedFile::fake()->createWithContent('4700157667_rev1.pdf', $pdfContent),
            $this->officeStaffA,
            false
        );
        $revPayload = $upload2->activePayload();
        $revPayload['items'][0]['ordered_qty'] = 350.000;
        $revPayload['items'][0]['line_total'] = 438900.00; // 350 * 1254
        $revPayload['totals']['base_value'] = 1851400.00;
        $revPayload['totals']['cgst_amount'] = 166626.00;
        $revPayload['totals']['sgst_amount'] = 166626.00;
        $revPayload['totals']['total_taxes'] = 333252.00;
        $revPayload['totals']['total_po_value'] = 2184652.00;

        $uploadService->saveReviewEdits($upload2, $revPayload, $this->officeStaffA);
        $res2 = $uploadService->confirmUpload($upload2, $this->seniorManagerA, null, null, false);

        // Must open as Revision 1 and create NO second site!
        $this->assertTrue($res2['is_revision']);
        $this->assertSame(1, Site::count());
        $this->assertSame(1, WorkOrder::count());

        $wo->refresh();
        $this->assertSame(1, (int) $wo->current_revision);
        $this->assertSame(1851400.00, (float) $wo->base_order_value);

        // Old bills 1..3 still show Revision 0
        $this->assertSame(0, (int) $bill1->fresh()->work_order_revision);
        $this->assertSame(0, (int) $bill2->fresh()->work_order_revision);
        $this->assertSame(0, (int) $bill3->fresh()->work_order_revision);

        // RA Bill 4: claim the 40 M3 earlier excess on line 1.1 -> now billable = 40, amount = 50,160.00!
        $bill4 = $raBillingService->createOrRecalculateRaBill($wo->fresh(['items', 'company']), [
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'items' => [
                $itemsBySr['1.1']->id => ['claimed_quantity' => 40, 'hold_quantity' => 0],
            ],
        ], $this->officeStaffA);

        $this->assertSame(1, (int) $bill4->work_order_revision);
        $this->assertSame(50160.00, (float) $bill4->current_bill_amount);
        $this->assertSame(4514.40, (float) $bill4->cgst_amount);
        $this->assertSame(4514.40, (float) $bill4->sgst_amount);
        $this->assertSame(59188.80, (float) $bill4->gross_amount);
    }

    public function test_scanned_pdf_reports_unreadable_and_supports_csv_line_import_and_enforces_access_rules(): void
    {
        // 1. Supervisor is forbidden from uploading a PO
        $scannedPdf = UploadedFile::fake()->createWithContent('scanned_image_only.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF");

        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('work_orders.uploads.store'), [
                'pdf_file' => $scannedPdf,
            ])
            ->assertForbidden();

        // 2. Office staff uploads scanned PDF -> marked as unreadable with fallback message
        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('work_orders.uploads.store'), [
                'pdf_file' => $scannedPdf,
            ])
            ->assertRedirect();

        $upload = WorkOrderUpload::firstOrFail();
        $this->assertTrue($upload->is_scanned_or_unreadable);
        $this->assertSame(WorkOrderUpload::STATUS_UNREADABLE, $upload->status);

        // 3. Cross-company user cannot view review screen or download private PDF (404 via tenant scope or 403 via policy)
        $crossReview = $this->actingAs($this->adminB)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->get(route('work_orders.uploads.review', $upload));
        $this->assertContains($crossReview->status(), [403, 404]);

        $crossPdf = $this->actingAs($this->adminB)
            ->withSession(['current_company_id' => $this->companyB->id])
            ->get(route('work_orders.uploads.pdf', $upload));
        $this->assertContains($crossPdf->status(), [403, 404]);

        // 4. Office staff imports BOQ lines via CSV fallback
        $csvContent = "Sr No,Service Code,Description,Specification,Unit,Qty,Rate,Total\n"
            . "1.1,SCV7212000000218,RCC,M25 Concrete,M3,100,1254,125400\n";
        $csvFile = UploadedFile::fake()->createWithContent('boq_lines.csv', $csvContent);

        $this->actingAs($this->officeStaffA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('work_orders.uploads.import_lines', $upload), [
                'lines_file' => $csvFile,
            ])
            ->assertRedirect(route('work_orders.uploads.review', $upload));

        $upload->refresh();
        $this->assertFalse($upload->is_scanned_or_unreadable);
        $this->assertCount(1, $upload->activePayload()['items']);
        $this->assertSame(125400.00, (float) $upload->activePayload()['totals']['base_value']);
    }

    public function test_lifecycle_transitions_retention_defect_liability_completion_alerts_and_attach_to_existing_site(): void
    {
        TenantContext::setCompanyId($this->companyA->id);

        $uploadService = app(WorkOrderUploadService::class);
        $pdf157667 = file_get_contents(base_path('tests/Fixtures/jsw_po_4700157667.pdf'));
        $pdf162257 = file_get_contents(base_path('tests/Fixtures/jsw_po_4700162257.pdf'));

        // 1. Upload & confirm PO 4700157667 -> creates Site 1
        $up1 = $uploadService->uploadAndExtract(
            $this->companyA->id,
            UploadedFile::fake()->createWithContent('4700157667.pdf', $pdf157667),
            $this->officeStaffA,
            false
        );
        $res1 = $uploadService->confirmUpload($up1, $this->officeStaffA, null, null, false);
        $site = $res1['site'];
        $wo1 = $res1['work_order'];

        // 2. Upload PO 4700162257 and attach to existing Site 1 -> no second site created
        $up2 = $uploadService->uploadAndExtract(
            $this->companyA->id,
            UploadedFile::fake()->createWithContent('4700162257.pdf', $pdf162257),
            $this->officeStaffA,
            false
        );
        $res2 = $uploadService->confirmUpload($up2, $this->officeStaffA, $site->id, null, false);
        $wo2 = $res2['work_order'];

        $this->assertSame(1, Site::count());
        $this->assertSame($site->id, $wo2->site_id);

        // 3. Completion date alerts (30d, 7d, overdue)
        $wo1->update(['completion_date' => now()->addDays(20)->toDateString()]);
        $this->assertSame('due_30_days', $wo1->fresh()->completionAlertState());

        $wo1->update(['completion_date' => now()->addDays(5)->toDateString()]);
        $this->assertSame('due_7_days', $wo1->fresh()->completionAlertState());

        $wo1->update(['completion_date' => now()->subDays(2)->toDateString()]);
        $this->assertSame('overdue', $wo1->fresh()->completionAlertState());

        // 4. Assign siteManagerA to site and transition through lifecycle stages + 5% retention & 12-month defect liability
        $site->update([
            'latitude' => 18.6969,
            'longitude' => 73.0355,
            'geofence_radius_m' => 200,
            'site_manager_id' => $this->siteManagerA->id,
        ]);

        foreach ([
            WorkOrder::LIFECYCLE_IN_PROGRESS,
            WorkOrder::LIFECYCLE_WORK_COMPLETE,
            WorkOrder::LIFECYCLE_FINAL_BILL_ISSUED,
            WorkOrder::LIFECYCLE_DEFECT_LIABILITY,
            WorkOrder::LIFECYCLE_CLOSED,
        ] as $targetStage) {
            $this->actingAs($this->siteManagerA)
                ->withSession(['current_company_id' => $this->companyA->id])
                ->post(route('work_orders.lifecycle', $wo1), [
                    'lifecycle_status' => $targetStage,
                ])
                ->assertSessionHasNoErrors();
        }

        $wo1->refresh();
        $this->assertSame(WorkOrder::LIFECYCLE_CLOSED, $wo1->effectiveLifecycleStatus());
        $this->assertNotNull($wo1->defect_liability_ends_on);
        $this->assertNotNull($wo1->retention_released_at);
        $this->assertSame('normal', $wo1->completionAlertLevel());

        // 5. Site workOrderSummary()
        $summary = $site->workOrderSummary();
        $this->assertSame(2, $summary['work_orders_count']);
        $this->assertSame(round(1788700.00 + 6338917.50, 2), $summary['ordered_base_value']);
    }
}
