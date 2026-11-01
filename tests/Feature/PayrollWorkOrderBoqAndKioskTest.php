<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollWorkOrderBoqAndKioskTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected Site $site;
    protected User $admin;
    protected User $siteManager;
    protected User $seniorManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction Pvt. Ltd.',
            'code' => 'NSD',
            'is_active' => true,
        ]);

        TenantContext::setCompanyId($this->company->id);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-NH48',
            'name' => 'NH-48 Highway Widening',
            'status' => 'active',
        ]);

        $this->site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-MUM-01',
            'name' => 'Mumbai Expressway Package 1',
            'status' => 'active',
        ]);

        $adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);
        $smRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['work_orders.create', 'work_orders.approve_site', 'work_orders.view', 'attendance.record'],
        ]);
        $vpRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => ['work_orders.approve_senior', 'work_orders.view'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->companies()->attach($this->company->id, ['role_id' => $adminRole->id]);

        $this->siteManager = User::factory()->create(['is_active' => true]);
        $this->siteManager->companies()->attach($this->company->id, ['role_id' => $smRole->id]);
        $this->site->assignedUsers()->attach($this->siteManager->id);

        $this->seniorManager = User::factory()->create(['is_active' => true]);
        $this->seniorManager->companies()->attach($this->company->id, ['role_id' => $vpRole->id]);
    }

    public function test_category_based_pay_types_and_full_payroll_lifecycle_with_advances_and_payslip(): void
    {
        // 1. Daily wage worker (₹800/day, OT ₹120/hr)
        $dailyWorker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'direct_labour',
            'code' => 'EMP-2026-0101',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'trade_designation' => 'Mason',
            'assigned_site_id' => $this->site->id,
            'pay_type' => 'daily',
            'daily_rate' => 800.00,
            'overtime_rate' => 120.00,
            'status' => 'active',
        ]);

        // 2. Monthly salary worker (₹30,000/month over 30-day period = ₹1,000/day)
        $monthlyWorker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-2026-0102',
            'first_name' => 'Sandeep',
            'last_name' => 'Kulkarni',
            'trade_designation' => 'Site Engineer',
            'assigned_site_id' => $this->site->id,
            'pay_type' => 'monthly',
            'daily_rate' => 1000.00,
            'monthly_salary' => 30000.00,
            'overtime_rate' => 150.00,
            'status' => 'active',
        ]);

        // 3. Hourly rate worker (₹150/hr)
        $hourlyWorker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-2026-0103',
            'first_name' => 'Ganesh',
            'last_name' => 'More',
            'trade_designation' => 'Excavator Operator',
            'assigned_site_id' => $this->site->id,
            'pay_type' => 'hourly',
            'daily_rate' => 1200.00,
            'hourly_rate' => 150.00,
            'overtime_rate' => 200.00,
            'status' => 'active',
        ]);

        // 4. Fixed Contract worker (₹25,000 fixed contract)
        $contractWorker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'subcontractor_worker',
            'code' => 'SUB-2026-0104',
            'first_name' => 'Mahesh',
            'last_name' => 'Shinde',
            'trade_designation' => 'Surveyor',
            'assigned_site_id' => $this->site->id,
            'pay_type' => 'contract',
            'daily_rate' => 900.00,
            'contract_rate' => 25000.00,
            'overtime_rate' => 0.00,
            'status' => 'active',
        ]);

        // Record attendance in November 2026 (30-day month: 2026-11-01 to 2026-11-30)
        // Daily worker: 2 present days, 2 hrs overtime => base = 1600, OT = 240, gross = 1840
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'worker_id' => $dailyWorker->id,
            'attendance_date' => '2026-11-02',
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00', // 10h => 8h reg + 2h OT
            'status' => 'present',
        ]);
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'worker_id' => $dailyWorker->id,
            'attendance_date' => '2026-11-03',
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
            'status' => 'present',
        ]);

        // Monthly worker: 15 present days out of 30 => base = 15,000
        for ($d = 1; $d <= 15; $d++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->site->id,
                'worker_id' => $monthlyWorker->id,
                'attendance_date' => sprintf('2026-11-%02d', $d),
                'clock_in' => '09:00:00',
                'clock_out' => '17:00:00',
                'status' => 'present',
            ]);
        }

        // Hourly worker: 8 regular hours + 2 OT hours => base = 8 * 150 = 1200, OT = 2 * 200 = 400, gross = 1600
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'worker_id' => $hourlyWorker->id,
            'attendance_date' => '2026-11-05',
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
            'status' => 'present',
        ]);

        // Contract worker: 1 present day => fixed contract = 25,000
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site->id,
            'worker_id' => $contractWorker->id,
            'attendance_date' => '2026-11-06',
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
            'status' => 'present',
        ]);

        // Record ₹500 Salary Advance for dailyWorker
        $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.advances.store'), [
                'worker_id' => $dailyWorker->id,
                'advance_date' => '2026-11-10',
                'amount' => 500.00,
                'reason' => 'Site cash advance',
            ])->assertRedirect();

        // Generate Payroll Period for Nov 2026
        $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.store'), [
                'title' => 'November 2026 Site Payroll',
                'site_id' => $this->site->id,
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
            ])->assertRedirect();

        $period = PayrollPeriod::firstOrFail();
        $this->assertSame(4, $period->total_workers);

        $dailyEntry = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $dailyWorker->id)->firstOrFail();
        $this->assertEquals(1600.00, (float) $dailyEntry->base_pay);
        $this->assertEquals(240.00, (float) $dailyEntry->overtime_pay);
        $this->assertEquals(1840.00, (float) $dailyEntry->gross_pay);
        $this->assertEquals(500.00, (float) $dailyEntry->advance_deduction);
        $this->assertEquals(1340.00, (float) $dailyEntry->net_pay);

        $monthlyEntry = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $monthlyWorker->id)->firstOrFail();
        $this->assertEquals(15000.00, (float) $monthlyEntry->base_pay);

        $hourlyEntry = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $hourlyWorker->id)->firstOrFail();
        $this->assertEquals(1200.00, (float) $hourlyEntry->base_pay);
        $this->assertEquals(400.00, (float) $hourlyEntry->overtime_pay);
        $this->assertEquals(1600.00, (float) $hourlyEntry->gross_pay);

        $contractEntry = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $contractWorker->id)->firstOrFail();
        $this->assertEquals(25000.00, (float) $contractEntry->base_pay);

        // Approve & Lock Payroll Period
        $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('payroll.lock', $period))
            ->assertRedirect();

        $this->assertSame('locked', $period->fresh()->status);
        $this->assertSame('deducted', SalaryAdvance::first()->status);

        // View Printable Payslip
        $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('payroll.payslip', [$period, $dailyEntry]))
            ->assertOk()
            ->assertSee('EMP-2026-0101')
            ->assertSee('1,340.00');
    }

    public function test_work_order_boq_template_generation_and_printable_approved_document(): void
    {
        $response = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('work_orders.store'), [
                'project_id' => $this->project->id,
                'site_id' => $this->site->id,
                'order_type' => 'internal',
                'client_reference' => 'NHAI-PKG1-BOQ-09',
                'contractor_name' => 'Shree Sai Earthmovers',
                'contractor_gst' => '27AABCS1429B1Z1',
                'contractor_contact' => 'Rajesh Patil (+91 9820111222)',
                'title' => 'Chainage 12+000 to 14+500 Box Culvert Earthwork',
                'description' => 'Excavation and embankment compaction',
                'boq_items' => [
                    ['description' => 'Hard Rock Excavation', 'unit' => 'Cum', 'quantity' => 200, 'rate' => 300],
                    ['description' => 'Murrum Backfilling & Compaction', 'unit' => 'Cum', 'quantity' => 100, 'rate' => 200],
                ],
                'estimated_amount' => 0, // Should auto-calculate to (200*300 + 100*200) = 80,000
            ]);

        $response->assertRedirect();
        $wo = WorkOrder::latest('id')->firstOrFail();
        $this->assertEquals(80000.00, (float) $wo->estimated_amount);
        $this->assertCount(2, $wo->boq_items);

        // Tier 1 Site Manager Approval + Tier 2 Senior Manager Approval
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('work_orders.approve_site', $wo), ['notes' => 'BOQ quantities verified on site'])
            ->assertRedirect();

        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('work_orders.approve_senior', $wo), ['notes' => 'Approved for execution'])
            ->assertRedirect();

        // View Printable Work Order / BOQ Document
        $printResp = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('work_orders.print', $wo));

        $printResp->assertOk()
            ->assertSee($wo->order_number)
            ->assertSee('Shree Sai Earthmovers')
            ->assertSee('Hard Rock Excavation')
            ->assertSee('80,000.00')
            ->assertSee('FINAL APPROVED FOR EXECUTION');
    }

    public function test_stateless_site_kiosk_mode_records_qr_and_pin_punches_with_no_persistent_worker_session(): void
    {
        $token = $this->site->ensureKioskToken();
        $this->assertNotEmpty($token);

        $worker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-2026-0501',
            'first_name' => 'Ajay',
            'last_name' => 'Chavan',
            'trade_designation' => 'JCB Operator',
            'assigned_site_id' => $this->site->id,
            'daily_rate' => 950.00,
            'pin' => Hash::make('654321'),
            'must_change_pin' => false,
            'status' => 'active',
        ]);
        $qrToken = $worker->ensureQrToken();

        // 1. Punch IN via QR Badge on Kiosk Terminal
        $punchInResp = $this->post(route('kiosk.punch', $token), [
            'mode' => 'qr',
            'qr_token' => $qrToken,
            'punch_type' => 'in',
        ]);

        $punchInResp->assertRedirect(route('kiosk.terminal', $token));
        $this->assertNull(session('worker_portal_id'), 'Kiosk must not persist a worker session between scans.');
        $this->assertDatabaseHas('attendance_punches', [
            'worker_id' => $worker->id,
            'site_id' => $this->site->id,
            'punch_type' => 'in',
            'source' => 'kiosk',
        ]);

        // 2. Punch OUT via Worker Code + 6-digit PIN on Kiosk Terminal
        $punchOutResp = $this->post(route('kiosk.punch', $token), [
            'mode' => 'pin',
            'worker_code' => 'EMP-2026-0501',
            'pin' => '654321',
            'punch_type' => 'out',
        ]);

        $punchOutResp->assertRedirect(route('kiosk.terminal', $token));
        $this->assertNull(session('worker_portal_id'));
        $this->assertDatabaseHas('attendance_punches', [
            'worker_id' => $worker->id,
            'site_id' => $this->site->id,
            'punch_type' => 'out',
            'source' => 'kiosk',
        ]);

        // 3. Rotate Kiosk Token as Site Manager and verify old token is invalidated
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('attendance.kiosk.rotate', $this->site))
            ->assertRedirect();

        $newToken = $this->site->fresh()->kiosk_token;
        $this->assertNotSame($token, $newToken);

        $this->get(route('kiosk.terminal', $token))->assertNotFound();
        $this->get(route('kiosk.terminal', $newToken))->assertOk();
    }
}
