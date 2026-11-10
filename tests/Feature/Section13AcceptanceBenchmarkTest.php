<?php

namespace Tests\Feature;

use App\Models\AttendanceLock;
use App\Models\AttendancePunch;
use App\Models\Client;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\MeasurementEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\Operations\RaBillExportService;
use App\Services\Operations\RaBillingService;
use App\Services\Security\TenantContext;
use App\Services\Security\TotpAuthenticatorService;
use App\Services\Workforce\AttendanceService;
use App\Services\Workforce\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class Section13AcceptanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Role $adminRole;
    private Role $siteManagerRole;
    private User $admin;
    private Project $project;
    private Site $site;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Construction Pvt Ltd',
            'code' => 'NSD',
            'is_active' => true,
            'invoice_prefix' => 'CIVIL',
            'invoice_next_sequence' => 21,
            'invoice_rounding_mode' => 'paisa',
        ]);

        TenantContext::setCompanyId($this->company->id);

        $this->adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->siteManagerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['work_orders.view', 'work_orders.create', 'attendance.view', 'attendance.record'],
        ]);

        $this->admin = User::create([
            'name' => 'Benchmark Admin',
            'email' => 'benchmark.admin@nsdconstruction.in',
            'password' => Hash::make('StrongPass!2026'),
            'is_active' => true,
        ]);
        $this->admin->companies()->attach($this->company->id, [
            'role_id' => $this->adminRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-JSW-01',
            'name' => 'JSW Dolvi Civil Works',
            'client_name' => 'JSW Steel Ltd',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-DOLVI-01',
            'name' => 'JSW Dolvi Plant Phase II',
            'status' => 'active',
            'latitude' => 18.6969000,
            'longitude' => 73.0389000,
            'geofence_radius_m' => 250,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'code' => 'CLI-JSW',
            'name' => 'JSW Steel Limited',
            'gstin' => '27AAACJ4323N1ZG',
            'state_name' => 'Maharashtra',
            'state_code' => '27',
            'is_active' => true,
        ]);
    }

    /**
     * Section 13.2: 4-decimal input 12.3456 M3 rounds HALF_UP to 12.346 M3 and computes exact amount 15,481.88.
     */
    public function test_section_13_2_measurement_4_decimal_input_rounds_to_12_346_and_amount_15481_88(): void
    {
        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'client_id' => $this->client->id,
            'order_number' => '4700168089',
            'title' => 'Excavation & RCC Foundation Works',
            'order_type' => 'client_issued',
            'status' => 'senior_manager_approved',
            'lifecycle_status' => WorkOrder::LIFECYCLE_CONFIRMED,
            'is_draft_po' => false,
            'estimated_amount' => 125400.00,
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'created_by' => $this->admin->id,
        ]);

        $item = WorkOrderItem::create([
            'company_id' => $this->company->id,
            'work_order_id' => $workOrder->id,
            'line_no' => 1,
            'sr_no' => '10',
            'description' => 'RCC M25 Foundation Concrete',
            'unit' => 'M3',
            'ordered_quantity' => 100.000,
            'rate' => 1254.00,
            'order_amount' => 125400.00,
        ]);

        // Input 4-decimal measurement 12.3456 -> rounds HALF_UP to 3 decimals: 12.346
        $entry = MeasurementEntry::create([
            'company_id' => $this->company->id,
            'work_order_id' => $workOrder->id,
            'work_order_item_id' => $item->id,
            'site_id' => $this->site->id,
            'measured_on' => '2026-11-01',
            'nos' => 1.000,
            'length' => 12.3456,
            'breadth' => 1.000,
            'depth_height' => 1.000,
            'status' => 'verified',
            'recorded_by' => $this->admin->id,
            'verified_by' => $this->admin->id,
            'verified_at' => now(),
        ]);

        $this->assertSame(12.346, (float) $entry->computed_quantity);

        $lineCalc = RaBillingService::computeLineItemBill(100.000, 0.000, (float) $entry->computed_quantity, 0.000, 1254.00);
        $this->assertSame(12.346, $lineCalc['current_billable_quantity']);
        $this->assertSame(15481.88, $lineCalc['current_amount']);
    }

    /**
     * Section 13.6: Invoice sequence {PREFIX}/{SEQ}/{FY} with April-March FY rollover and idempotent re-export.
     */
    public function test_section_13_6_invoice_sequence_format_and_financial_year_rollover(): void
    {
        $exportService = app(RaBillExportService::class);
        $billingService = app(RaBillingService::class);

        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'client_id' => $this->client->id,
            'order_number' => '4700168090',
            'title' => 'Civil Structural Works',
            'order_type' => 'client_issued',
            'status' => 'senior_manager_approved',
            'lifecycle_status' => WorkOrder::LIFECYCLE_CONFIRMED,
            'is_draft_po' => false,
            'estimated_amount' => 500000.00,
            'created_by' => $this->admin->id,
        ]);

        $woItem = WorkOrderItem::create([
            'company_id' => $this->company->id,
            'work_order_id' => $workOrder->id,
            'line_no' => 1,
            'sr_no' => '10',
            'description' => 'Structural Concrete',
            'unit' => 'M3',
            'ordered_quantity' => 500.000,
            'rate' => 1000.00,
            'order_amount' => 500000.00,
        ]);

        // Bill 1 on 2026-06-15 -> CIVIL/21/26-27
        $bill1 = $billingService->createOrRecalculateRaBill($workOrder, [
            'invoice_date' => '2026-06-15',
            'items' => [$woItem->id => ['claimed_quantity' => 50, 'hold_quantity' => 0]],
        ], $this->admin);
        $bill1->update(['status' => 'approved']);
        $inv1 = $exportService->ensureInvoiceNumberAssigned($bill1);
        $this->assertSame('CIVIL/21/26-27', $inv1);
        $this->assertSame('CIVIL/21/26-27', $bill1->fresh()->invoice_number);

        // Re-exporting same bill is idempotent and does not increment sequence
        $inv1Repeat = $exportService->ensureInvoiceNumberAssigned($bill1->fresh());
        $this->assertSame('CIVIL/21/26-27', $inv1Repeat);

        // Bill 2 on 2027-03-31 -> CIVIL/22/26-27
        $bill2 = $billingService->createOrRecalculateRaBill($workOrder, [
            'invoice_date' => '2027-03-31',
            'items' => [$woItem->id => ['claimed_quantity' => 50, 'hold_quantity' => 0]],
        ], $this->admin);
        $bill2->update(['status' => 'approved']);
        $inv2 = $exportService->ensureInvoiceNumberAssigned($bill2);
        $this->assertSame('CIVIL/22/26-27', $inv2);

        // Bill 3 on 2027-04-01 (new Financial Year 27-28) -> starts a new FY sequence row -> CIVIL/21/27-28
        $bill3 = $billingService->createOrRecalculateRaBill($workOrder, [
            'invoice_date' => '2027-04-01',
            'items' => [$woItem->id => ['claimed_quantity' => 50, 'hold_quantity' => 0]],
        ], $this->admin);
        $bill3->update(['status' => 'approved']);
        $inv3 = $exportService->ensureInvoiceNumberAssigned($bill3);
        $this->assertSame('CIVIL/21/27-28', $inv3);

        // When company invoice_starting_number is 1, a new FY 28-29 starts at 01 -> CIVIL/01/28-29
        $this->company->update(['invoice_starting_number' => 1]);
        $bill4 = $billingService->createOrRecalculateRaBill($workOrder->fresh(), [
            'invoice_date' => '2028-04-01',
            'items' => [$woItem->id => ['claimed_quantity' => 50, 'hold_quantity' => 0]],
        ], $this->admin);
        $bill4->update(['status' => 'approved']);
        $inv4 = $exportService->ensureInvoiceNumberAssigned($bill4);
        $this->assertSame('CIVIL/01/28-29', $inv4);
    }

    /**
     * Section 13.7: 16-Line RA Bill PDF & Excel Export Paisa Parity.
     */
    public function test_section_13_7_sixteen_line_ra_bill_pdf_and_excel_export_paisa_parity(): void
    {
        $billingService = app(RaBillingService::class);
        $exportService = app(RaBillExportService::class);

        $workOrder = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->site->id,
            'client_id' => $this->client->id,
            'order_number' => '4700168091',
            'title' => '16-Line JSW Civil Package',
            'order_type' => 'client_issued',
            'status' => 'senior_manager_approved',
            'lifecycle_status' => WorkOrder::LIFECYCLE_CONFIRMED,
            'is_draft_po' => false,
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'rounding_mode' => 'paisa',
            'created_by' => $this->admin->id,
        ]);

        $itemsPayload = [];
        for ($i = 1; $i <= 16; $i++) {
            $orderedQty = round(25.0 + ($i * 3.125), 3);
            $rate = round(420.50 + ($i * 17.25), 2);
            $woItem = WorkOrderItem::create([
                'company_id' => $this->company->id,
                'work_order_id' => $workOrder->id,
                'line_no' => $i,
                'sr_no' => (string) ($i * 10),
                'description' => "Civil BOQ Item Line #{$i}",
                'unit' => 'M3',
                'ordered_quantity' => $orderedQty,
                'rate' => $rate,
                'order_amount' => round($orderedQty * $rate, 2),
            ]);

            $itemsPayload[$woItem->id] = [
                'claimed_quantity' => round($orderedQty * 0.45, 3),
                'hold_quantity' => 0.000,
            ];
        }

        $raBill = $billingService->createOrRecalculateRaBill($workOrder, [
            'invoice_date' => '2026-11-05',
            'rounding_mode' => 'paisa',
            'items' => $itemsPayload,
        ], $this->admin);

        $raBill->update(['status' => 'approved']);
        $exportService->ensureInvoiceNumberAssigned($raBill);
        $raBill->refresh();

        $this->assertCount(16, $raBill->items);

        // Export Excel (.xlsx) and Combined PDF (.pdf)
        $xlsxExport = $exportService->exportXlsx($raBill, $this->admin);
        $pdfExport = $exportService->exportPdf($raBill, 'combined_pdf', $this->admin);

        $xlsxPath = \Illuminate\Support\Facades\Storage::disk('local')->path($xlsxExport->file_path);
        $pdfPath = \Illuminate\Support\Facades\Storage::disk('local')->path($pdfExport->file_path);

        $this->assertFileExists($xlsxPath);
        $this->assertFileExists($pdfPath);

        // Load Excel workbook and verify exact paisa parity with database RA Bill across all 16 lines
        $spreadsheet = IOFactory::load($xlsxPath);
        $abstractSheet = $spreadsheet->getSheetByName('ABSTRACT');
        $invSheet = $spreadsheet->getSheetByName('INV');
        $this->assertNotNull($abstractSheet);
        $this->assertNotNull($invSheet);

        // With 16 items (rows 6..21), row 22 is Total Basic Value, 23 is CGST, 24 is SGST, 25 is Gross Total
        $foundSubTotal = round((float) $abstractSheet->getCell('L22')->getCalculatedValue(), 2);
        $foundCgst = round((float) $abstractSheet->getCell('L23')->getCalculatedValue(), 2);
        $foundSgst = round((float) $abstractSheet->getCell('L24')->getCalculatedValue(), 2);
        $foundGrossTotal = round((float) $abstractSheet->getCell('L25')->getCalculatedValue(), 2);

        $this->assertSame(round((float) $raBill->current_bill_amount, 2), $foundSubTotal);
        $this->assertSame(round((float) $raBill->cgst_amount, 2), $foundCgst);
        $this->assertSame(round((float) $raBill->sgst_amount, 2), $foundSgst);
        $this->assertSame(round((float) $raBill->gross_amount, 2), $foundGrossTotal);

        // Also check INV sheet cross-sheet formulas match to the exact paisa
        $this->assertSame(round((float) $raBill->current_bill_amount, 2), round((float) $invSheet->getCell('G14')->getCalculatedValue(), 2));
        $this->assertSame(round((float) $raBill->cgst_amount, 2), round((float) $invSheet->getCell('G15')->getCalculatedValue(), 2));
        $this->assertSame(round((float) $raBill->sgst_amount, 2), round((float) $invSheet->getCell('G16')->getCalculatedValue(), 2));

        // Verify PDF binary header and rendered invoice HTML parity
        $pdfBytes = file_get_contents($pdfPath);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        $renderedHtml = view('ra_bills.pdf.combined', [
            'workOrder' => $workOrder,
            'raBill' => $raBill->fresh(['items', 'workOrder.company', 'client', 'site']),
            'company' => $this->company,
            'amountInWords' => \App\Support\IndianCurrencyWords::toWords((float) $raBill->net_payable),
        ])->render();

        $this->assertStringContainsString(\App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->current_bill_amount, 2), $renderedHtml);
        $this->assertStringContainsString(\App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->cgst_amount, 2), $renderedHtml);
        $this->assertStringContainsString(\App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->sgst_amount, 2), $renderedHtml);
        $this->assertStringContainsString(\App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->gross_amount, 2), $renderedHtml);
    }

    /**
     * Section 13.9: Manager 2FA RFC 6238 TOTP & zero plaintext OTP in screen or logs.
     */
    public function test_section_13_9_manager_totp_2fa_never_exposes_plaintext_code_on_screen_or_logs(): void
    {
        config(['mail.default' => 'log']);
        $logPath = storage_path('logs/laravel.log');
        $logSizeBefore = file_exists($logPath) ? filesize($logPath) : 0;

        $manager = User::create([
            'name' => 'Vikram Manager',
            'email' => 'vikram.mgr@nsdconstruction.in',
            'password' => Hash::make('ManagerPass!2026'),
            'is_active' => true,
            'two_factor_enabled' => true,
        ]);
        $manager->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Issue email fallback code while mail.default=log and verify it is NOT written to laravel.log
        $issuedEmailOtp = $manager->issueTwoFactorCode(true);

        $this->post('/login', [
            'email' => 'vikram.mgr@nsdconstruction.in',
            'password' => 'ManagerPass!2026',
        ])->assertRedirect(route('two-factor.challenge'));

        $challengeHtml = $this->get('/two-factor-challenge')->getContent();
        $totpCode = app(TotpAuthenticatorService::class)->generateCode($manager->fresh()->two_factor_secret);

        // Confirm neither the 6-digit email OTP nor the 6-digit TOTP code appears on screen
        $this->assertStringNotContainsString('Local / Demo Verification Code', (string) $challengeHtml);
        $this->assertStringNotContainsString($issuedEmailOtp, (string) $challengeHtml);
        $this->assertStringNotContainsString($totpCode, (string) $challengeHtml);

        if (file_exists($logPath)) {
            $newLogContent = (string) file_get_contents($logPath, false, null, $logSizeBefore);
            $this->assertStringNotContainsString($issuedEmailOtp, $newLogContent);
            $this->assertStringNotContainsString($totpCode, $newLogContent);
        }

        // Verify login completes with the RFC 6238 TOTP code
        $this->post('/two-factor-challenge', ['code' => $totpCode])
            ->assertRedirect(route('dashboard'));
    }

    /**
     * Section 13.10: 5-Attempt Worker PIN Lockout & Authorized Manager Recovery.
     */
    public function test_section_13_10_worker_pin_five_attempt_lockout_and_manager_recovery(): void
    {
        $worker = Worker::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'W-EMP-901',
            'first_name' => 'Raju',
            'last_name' => 'More',
            'worker_type' => 'employee',
            'skill_category' => 'skilled',
            'trade_designation' => 'Surveyor',
            'pay_type' => 'daily',
            'daily_rate' => 900.00,
            'pin' => Hash::make('654321'),
            'must_change_pin' => false,
            'status' => 'active',
            'joining_date' => '2026-01-10',
        ]);

        // 5 failed PIN attempts
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('worker.login.post'), [
                'worker_code' => 'W-EMP-901',
                'pin' => '000000',
            ])->assertSessionHasErrors('worker_code');
        }

        // 6th attempt even with the VALID PIN '654321' is blocked by the 5-attempt lockout
        $lockedResponse = $this->post(route('worker.login.post'), [
            'worker_code' => 'W-EMP-901',
            'pin' => '654321',
        ]);
        $lockedResponse->assertSessionHasErrors('worker_code');
        $this->assertStringContainsString(
            'temporarily locked',
            (string) session('errors')->first('worker_code')
        );

        // Verify Company-Specific Lockout Isolation:
        // An identical worker_code 'W-EMP-901' in Company B is NOT locked out when Company A's 'W-EMP-901' is locked!
        $companyB = Company::create([
            'name' => 'Other Infrastructure Pvt Ltd',
            'code' => 'OTH',
            'is_active' => true,
        ]);
        $projectB = Project::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'code' => 'PRJ-OTH-01',
            'name' => 'Other Project',
            'status' => 'active',
        ]);
        $siteB = Site::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'project_id' => $projectB->id,
            'code' => 'SITE-OTH-01',
            'name' => 'Other Site',
            'status' => 'active',
            'kiosk_token' => 'kiosk-token-company-b-1234567890',
        ]);
        $workerB = Worker::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'assigned_site_id' => $siteB->id,
            'code' => 'W-EMP-901',
            'first_name' => 'Kiran',
            'last_name' => 'Patil',
            'worker_type' => 'employee',
            'skill_category' => 'skilled',
            'trade_designation' => 'Engineer',
            'pay_type' => 'daily',
            'daily_rate' => 950.00,
            'pin' => Hash::make('999888'),
            'must_change_pin' => false,
            'status' => 'active',
            'joining_date' => '2026-01-10',
        ]);

        $this->assertNotSame($worker->getPinLockoutCacheKey(), $workerB->getPinLockoutCacheKey());

        // Company B worker with identical code 'W-EMP-901' logs into Worker Portal and punches on Kiosk B without being blocked
        $companyBLogin = $this->post(route('worker.login.post'), [
            'worker_code' => 'W-EMP-901',
            'pin' => '999888',
        ]);
        $companyBLogin->assertRedirect(route('worker.punch'));
        $this->post(route('worker.logout'));

        $kioskBPunch = $this->post(route('kiosk.punch', $siteB->kiosk_token), [
            'mode' => 'pin',
            'worker_code' => 'W-EMP-901',
            'pin' => '999888',
            'punch_type' => 'in',
        ]);
        $kioskBPunch->assertRedirect(route('kiosk.terminal', $siteB->kiosk_token));
        $kioskBPunch->assertSessionHas('kiosk_punch_result');

        // Meanwhile, Company A's Kiosk ALSO enforces Company A's lockout for 'W-EMP-901'
        $this->site->ensureKioskToken();
        $kioskAPunchLocked = $this->post(route('kiosk.punch', $this->site->kiosk_token), [
            'mode' => 'pin',
            'worker_code' => 'W-EMP-901',
            'pin' => '654321',
            'punch_type' => 'in',
        ]);
        $kioskAPunchLocked->assertSessionHasErrors('worker_code');

        // Authorized Company Admin resets Company A worker's PIN via POST /workforce/{worker}/reset-pin
        $this->actingAs($this->admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('workforce.reset_pin', $worker), [
                'pin' => '789456',
            ])
            ->assertRedirect();

        $this->post('/logout');

        // Company A Worker can now immediately log in using the new temporary PIN and is directed to change PIN
        $recoveryLogin = $this->post(route('worker.login.post'), [
            'worker_code' => 'W-EMP-901',
            'pin' => '789456',
        ]);
        $recoveryLogin->assertRedirect(route('worker.pin.show'));
    }

    /**
     * Section 13.11: Exact 3-Worker Payroll Benchmark (19,400.00 / 28,000.00 / 25,800.00)
     * plus AttendancePunch and AttendanceLock immutability protections.
     */
    public function test_section_13_11_three_worker_payroll_benchmark_and_attendance_immutability(): void
    {
        // Worker 1: Daily rate ₹800, OT rate ₹150/hr, 22 present + 1 half_day + 1 paid_leave = 23.5 days (₹18,800) + 14 OT hrs (₹2,100) = ₹20,900 gross - ₹1,500 advance = ₹19,400 net
        $workerDaily = Worker::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'W-BENCH-01',
            'first_name' => 'Suresh',
            'last_name' => 'Daily',
            'worker_type' => 'direct_labour',
            'skill_category' => 'skilled',
            'trade_designation' => 'Mason',
            'pay_type' => 'daily',
            'daily_rate' => 800.00,
            'overtime_rate' => 150.00,
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ]);

        // Worker 2: Monthly salary ₹30,000 in 30-day November 2026, 26 present + 2 holiday = 28 effective days -> ₹28,000 net
        $workerMonthly = Worker::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'W-BENCH-02',
            'first_name' => 'Mahesh',
            'last_name' => 'Monthly',
            'worker_type' => 'employee',
            'skill_category' => 'supervisor',
            'trade_designation' => 'Foreman',
            'pay_type' => 'monthly',
            'monthly_salary' => 30000.00,
            'daily_rate' => 1000.00,
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ]);

        // Worker 3: Hourly rate ₹120/hr, OT rate ₹180/hr, 200 regular hrs (₹24,000) + 10 OT hrs (₹1,800) = ₹25,800 net
        $workerHourly = Worker::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->site->id,
            'code' => 'W-BENCH-03',
            'first_name' => 'Ganesh',
            'last_name' => 'Hourly',
            'worker_type' => 'subcontractor_worker',
            'skill_category' => 'skilled',
            'trade_designation' => 'Welder',
            'pay_type' => 'hourly',
            'hourly_rate' => 120.00,
            'overtime_rate' => 180.00,
            'daily_rate' => 960.00,
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ]);

        // Seed November 2026 attendance for Worker 1 (22 present, 1 half_day, 1 paid_leave, 14 OT hours)
        for ($d = 1; $d <= 22; $d++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->site->id,
                'project_id' => $this->project->id,
                'worker_id' => $workerDaily->id,
                'attendance_date' => sprintf('2026-11-%02d', $d),
                'status' => 'present',
                'worked_hours' => $d <= 7 ? 10.00 : 8.00,
                'overtime_hours' => $d <= 7 ? 2.00 : 0.00, // 7 * 2 = 14 OT hours
                'approval_status' => 'approved',
            ]);
        }
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'project_id' => $this->project->id,
            'worker_id' => $workerDaily->id,
            'attendance_date' => '2026-11-23',
            'status' => 'half_day',
            'worked_hours' => 4.00,
            'overtime_hours' => 0.00,
            'approval_status' => 'approved',
        ]);
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'project_id' => $this->project->id,
            'worker_id' => $workerDaily->id,
            'attendance_date' => '2026-11-24',
            'status' => 'paid_leave',
            'worked_hours' => 0.00,
            'overtime_hours' => 0.00,
            'approval_status' => 'approved',
        ]);

        SalaryAdvance::create([
            'company_id' => $this->company->id,
            'worker_id' => $workerDaily->id,
            'site_id' => $this->site->id,
            'advance_date' => '2026-11-10',
            'amount' => 1500.00,
            'deducted_amount' => 0.00,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        // Seed November 2026 attendance for Worker 2 (26 present + 2 holiday = 28 effective days in 30-day Nov)
        for ($d = 1; $d <= 28; $d++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->site->id,
                'project_id' => $this->project->id,
                'worker_id' => $workerMonthly->id,
                'attendance_date' => sprintf('2026-11-%02d', $d),
                'status' => $d <= 26 ? 'present' : 'holiday',
                'worked_hours' => $d <= 26 ? 8.00 : 0.00,
                'overtime_hours' => 0.00,
                'approval_status' => 'approved',
            ]);
        }

        // Seed November 2026 attendance for Worker 3 (25 days: 200 regular hrs + 10 OT hrs = 210 worked_hours)
        for ($d = 1; $d <= 25; $d++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->site->id,
                'project_id' => $this->project->id,
                'worker_id' => $workerHourly->id,
                'attendance_date' => sprintf('2026-11-%02d', $d),
                'status' => 'present',
                'worked_hours' => $d <= 5 ? 10.00 : 8.00,
                'overtime_hours' => $d <= 5 ? 2.00 : 0.00, // 5 * 2 = 10 OT hours; 210 - 10 = 200 regular hours
                'approval_status' => 'approved',
            ]);
        }

        $period = PayrollPeriod::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'period_code' => 'PAY-2026-11',
            'title' => 'November 2026 Site Payroll',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $payrollService = app(PayrollCalculationService::class);
        $period = $payrollService->calculatePeriod($period, $this->admin);

        $entries = $period->entries->keyBy('worker_id');
        $this->assertSame(19400.00, (float) $entries[$workerDaily->id]->net_pay);
        $this->assertSame(28000.00, (float) $entries[$workerMonthly->id]->net_pay);
        $this->assertSame(25800.00, (float) $entries[$workerHourly->id]->net_pay);
        $this->assertSame(73200.00, (float) $period->total_net_amount);

        // Verify AttendancePunch append-only protection
        $attendanceService = app(AttendanceService::class);
        $punch = $attendanceService->recordPunch($workerDaily, $this->site, [
            'punch_type' => 'in',
            'source' => 'self',
            'latitude' => 18.6969000,
            'longitude' => 73.0389000,
        ], $this->admin->id);

        try {
            $punch->update(['punch_type' => 'out']);
            $this->fail('Expected LogicException when updating an append-only AttendancePunch.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $punch->delete();
            $this->fail('Expected LogicException when deleting an append-only AttendancePunch.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Verify AttendanceLock stage transition & payroll_locked protection
        $lock = $attendanceService->transitionLockStage($this->site, '2026-11-01', 'payroll_locked', $this->admin->id);
        $this->assertSame('payroll_locked', $lock->stage);

        try {
            $lock->update(['stage' => 'submitted']);
            $this->fail('Expected LogicException when downgrading a payroll-locked AttendanceLock without admin unlock.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('payroll-locked', $e->getMessage());
        }

        // Authorized admin unlock with reason succeeds
        $unlocked = $attendanceService->transitionLockStage($this->site, '2026-11-01', 'open', $this->admin->id, 'Approved payroll correction');
        $this->assertSame('open', $unlocked->stage);
    }
}
