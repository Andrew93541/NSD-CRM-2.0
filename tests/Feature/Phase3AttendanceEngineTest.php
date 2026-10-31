<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceFlag;
use App\Models\AttendanceLock;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase3AttendanceEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected Site $siteMumbai;
    protected Site $sitePune;
    protected User $admin;
    protected User $seniorManager;
    protected User $siteManager;
    protected User $supervisor;
    protected Worker $empWorker;
    protected Worker $subWorker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Infrastructure Ltd',
            'code' => 'NSD',
            'is_active' => true,
        ]);
        TenantContext::setCompanyId($this->company->id);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'Mumbai-Pune Expressway Pkg 2',
            'code' => 'MPEW-02',
            'status' => 'active',
        ]);

        // Site 1: Mumbai Sector (19.0760, 72.8777), 200m radius
        $this->siteMumbai = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Mumbai Panvel Yard',
            'code' => 'SITE-MUM',
            'latitude' => 19.0760000,
            'longitude' => 72.8777000,
            'geofence_radius_m' => 200,
            'shift_start_time' => '09:00:00',
            'shift_end_time' => '18:00:00',
            'shift_hours' => 8.00,
            'status' => 'active',
        ]);

        // Site 2: Pune Sector (18.5204, 73.8567), ~120 km away
        $this->sitePune = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Pune Wakad Camp',
            'code' => 'SITE-PUN',
            'latitude' => 18.5204000,
            'longitude' => 73.8567000,
            'geofence_radius_m' => 200,
            'shift_start_time' => '09:00:00',
            'shift_end_time' => '18:00:00',
            'shift_hours' => 8.00,
            'status' => 'active',
        ]);

        $this->admin = $this->makeUser('Admin', 'admin@nsd.local', 'company_admin');
        $this->seniorManager = $this->makeUser('Senior Manager', 'senior@nsd.local', 'senior_manager');
        $this->siteManager = $this->makeUser('Site Manager', 'sitemgr@nsd.local', 'site_manager');
        $this->supervisor = $this->makeUser('Site Supervisor', 'sup@nsd.local', 'site_supervisor');

        $this->siteMumbai->assignedUsers()->attach([$this->siteManager->id, $this->supervisor->id]);
        $this->sitePune->assignedUsers()->attach([$this->siteManager->id, $this->supervisor->id]);

        $this->empWorker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-2026-0001',
            'first_name' => 'Vikram',
            'last_name' => 'Shinde',
            'trade_designation' => 'Survey Engineer',
            'assigned_site_id' => $this->siteMumbai->id,
            'daily_rate' => 1200,
            'overtime_rate' => 200,
            'status' => 'active',
            'pin' => Hash::make('111111'),
            'must_change_pin' => true,
        ]);

        $this->subWorker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'direct_labour',
            'code' => 'SUB-2026-0001',
            'first_name' => 'Ganesh',
            'last_name' => 'More',
            'trade_designation' => 'Helper',
            'assigned_site_id' => $this->siteMumbai->id,
            'daily_rate' => 700,
            'overtime_rate' => 100,
            'status' => 'active',
        ]);
    }

    protected function makeUser(string $name, string $email, string $roleSlug): User
    {
        $role = Role::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => $roleSlug],
            ['name' => ucfirst(str_replace('_', ' ', $roleSlug)), 'permissions' => ['*']]
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_worker_portal_login_forced_pin_change_and_server_time_geofence_punch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:05:00'));

        // 1. Subcontractor/Direct labour cannot log into Worker Portal
        $this->post('/worker/login', [
            'worker_code' => $this->subWorker->code,
            'pin' => '111111',
        ])->assertSessionHasErrors('worker_code');

        // 2. Employee logs in with temporary PIN and is forced to change PIN
        $loginResp = $this->post('/worker/login', [
            'worker_code' => $this->empWorker->code,
            'pin' => '111111',
        ]);
        $loginResp->assertRedirect(route('worker.pin.show'));

        // Attempting to access punch screen before changing PIN redirects back to PIN screen
        $this->get('/worker/punch')->assertRedirect(route('worker.pin.show'));

        // Change PIN to 654321
        $this->post('/worker/pin', [
            'new_pin' => '654321',
            'new_pin_confirmation' => '654321',
        ])->assertRedirect(route('worker.punch'));

        $this->empWorker->refresh();
        $this->assertFalse($this->empWorker->must_change_pin);

        // 3. Punch IN inside Mumbai site geofence (19.0761, 72.8778 is ~15m away), passing a fake client timestamp
        $this->post('/worker/punch', [
            'site_id' => $this->siteMumbai->id,
            'punch_type' => 'in',
            'latitude' => 19.0761000,
            'longitude' => 72.8778000,
            'device_id' => 'DEV-VIKRAM-01',
            'punched_at' => '2020-01-01 00:00:00', // Must be ignored in favor of server time
        ])->assertRedirect(route('worker.punch'));

        $punchIn = AttendancePunch::where('worker_id', $this->empWorker->id)->first();
        $this->assertNotNull($punchIn);
        $this->assertEquals('in', $punchIn->punch_type);
        $this->assertEquals('2026-10-31 09:05:00', $punchIn->punched_at->format('Y-m-d H:i:s'));
        $this->assertTrue($punchIn->within_geofence);
        $this->assertEquals(0, AttendanceFlag::where('worker_id', $this->empWorker->id)->where('flag_type', 'outside_geofence')->count());

        // 4. Advance server clock to 19:05 (10 hours worked -> 2 hours overtime) and punch OUT outside geofence (~1.5 km away)
        Carbon::setTestNow(Carbon::parse('2026-10-31 19:05:00'));

        $this->post('/worker/punch', [
            'site_id' => $this->siteMumbai->id,
            'punch_type' => 'out',
            'latitude' => 19.0900000,
            'longitude' => 72.8900000,
            'device_id' => 'DEV-VIKRAM-01',
        ])->assertRedirect(route('worker.punch'));

        $att = DailyAttendance::where('worker_id', $this->empWorker->id)->first();
        $this->assertNotNull($att);
        $this->assertEquals(10.00, (float) $att->worked_hours);
        $this->assertEquals(2.00, (float) $att->overtime_hours);

        // Out-of-fence punch is stored AND flagged
        $this->assertDatabaseHas('attendance_flags', [
            'worker_id' => $this->empWorker->id,
            'site_id' => $this->siteMumbai->id,
            'flag_type' => 'outside_geofence',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }

    public function test_shared_device_and_impossible_travel_anomaly_flags_and_supervisor_qr_scan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 08:30:00'));

        // Create second employee worker
        $emp2 = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-2026-0002',
            'first_name' => 'Suresh',
            'last_name' => 'Patil',
            'trade_designation' => 'Foreman',
            'assigned_site_id' => $this->siteMumbai->id,
            'daily_rate' => 1000,
            'status' => 'active',
            'pin' => Hash::make('222222'),
            'must_change_pin' => false,
        ]);

        $this->empWorker->update(['must_change_pin' => false]);

        // Worker 1 punches with device 'SHARED-PHONE-99'
        $this->withSession([
            'worker_auth_id' => $this->empWorker->id,
            'worker_company_id' => $this->company->id,
        ])->post('/worker/punch', [
            'site_id' => $this->siteMumbai->id,
            'punch_type' => 'in',
            'latitude' => 19.0760000,
            'longitude' => 72.8777000,
            'device_id' => 'SHARED-PHONE-99',
        ])->assertRedirect(route('worker.punch'));

        // Worker 2 punches 2 minutes later using the exact same device 'SHARED-PHONE-99'
        Carbon::setTestNow(Carbon::parse('2026-10-31 08:32:00'));
        $this->withSession([
            'worker_auth_id' => $emp2->id,
            'worker_company_id' => $this->company->id,
        ])->post('/worker/punch', [
            'site_id' => $this->siteMumbai->id,
            'punch_type' => 'in',
            'latitude' => 19.0760000,
            'longitude' => 72.8777000,
            'device_id' => 'SHARED-PHONE-99',
        ])->assertRedirect(route('worker.punch'));

        $this->assertDatabaseHas('attendance_flags', [
            'worker_id' => $emp2->id,
            'flag_type' => 'shared_device',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        // Impossible travel: Worker 1 is scanned at Pune site (~120 km away) just 15 minutes after Mumbai punch
        Carbon::setTestNow(Carbon::parse('2026-10-31 08:45:00'));
        $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/qr-scan', [
                'site_id' => $this->sitePune->id,
                'badge_token' => $this->empWorker->ensureQrToken(),
                'punch_type' => 'in',
            ])->assertRedirect();

        $this->assertDatabaseHas('attendance_flags', [
            'worker_id' => $this->empWorker->id,
            'site_id' => $this->sitePune->id,
            'flag_type' => 'impossible_travel',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }

    public function test_supervisor_same_day_rule_and_three_stage_locks(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // 1. Supervisor cannot mark back-dated muster roll (403)
        $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/muster', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $yesterday,
                'workers' => [
                    $this->subWorker->id => [
                        ['status' => 'present', 'clock_in' => '09:00', 'clock_out' => '18:00'],
                    ],
                ],
            ])->assertStatus(403);

        // 2. Supervisor CAN mark today's muster roll -> auto-transitions lock stage to 'submitted'
        $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/muster', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'workers' => [
                    $this->subWorker->id => [
                        ['status' => 'present', 'clock_in' => '09:00', 'clock_out' => '18:00'],
                    ],
                ],
            ])->assertRedirect();

        $this->assertDatabaseHas('attendance_locks', [
            'site_id' => $this->siteMumbai->id,
            'stage' => 'submitted',
        ]);

        // 3. Supervisor cannot lock the day (site_locked -> 403)
        $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/lock', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'stage' => 'site_locked',
            ])->assertStatus(403);

        // 4. Site Manager approves & locks the day (site_locked -> 302)
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/lock', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'stage' => 'site_locked',
            ])->assertRedirect();

        // 5. Once locked, direct muster roll edits are blocked (403)
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/muster', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'workers' => [
                    $this->subWorker->id => [
                        ['status' => 'absent'],
                    ],
                ],
            ])->assertStatus(403);

        // 6. Site Manager cannot apply Payroll Lock (403); Senior Manager CAN apply Payroll Lock
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/lock', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'stage' => 'payroll_locked',
            ])->assertStatus(403);

        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/lock', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'stage' => 'payroll_locked',
            ])->assertRedirect();

        $this->assertDatabaseHas('attendance_locks', [
            'site_id' => $this->siteMumbai->id,
            'stage' => 'payroll_locked',
        ]);

        // 7. Only Admin can unlock a locked day, with mandatory unlock_reason
        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/lock', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'stage' => 'open',
                'unlock_reason' => 'Senior manager trying to unlock',
            ])->assertStatus(403);

        $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/lock', [
                'site_id' => $this->siteMumbai->id,
                'attendance_date' => $today,
                'stage' => 'open',
                'unlock_reason' => 'Reopened for verified overtime adjustment.',
            ])->assertRedirect();

        $this->assertDatabaseHas('attendance_locks', [
            'site_id' => $this->siteMumbai->id,
            'stage' => 'open',
            'unlock_reason' => 'Reopened for verified overtime adjustment.',
        ]);
    }

    public function test_non_self_approval_on_corrections_and_leave_to_attendance_and_payroll_export(): void
    {
        $date = '2026-10-29';

        $att = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteMumbai->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->empWorker->id,
            'attendance_date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '13:00:00',
            'status' => 'half_day',
        ]);

        // Site Manager requests a correction
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/attendance/{$att->id}/correction", [
                'proposed_clock_in' => '09:00',
                'proposed_clock_out' => '19:00',
                'proposed_status' => 'present',
                'reason' => 'Evening concrete pour shift extension verified.',
            ])->assertRedirect();

        $correction = AttendanceCorrection::where('daily_attendance_id', $att->id)->first();
        $this->assertNotNull($correction);

        // Site Manager CANNOT approve their own correction request (403)
        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/attendance/corrections/{$correction->id}/review", [
                'action' => 'approved',
            ])->assertStatus(403);

        // Senior Manager (different user) CAN approve the correction
        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/attendance/corrections/{$correction->id}/review", [
                'action' => 'approved',
                'review_notes' => 'Verified with site logbook.',
            ])->assertRedirect();

        $att->refresh();
        $this->assertEquals('present', $att->status);
        $this->assertEquals(10.00, (float) $att->worked_hours);
        $this->assertEquals(2.00, (float) $att->overtime_hours);

        // Submit and approve a Paid Leave Request -> automatically creates DailyAttendance with status='paid_leave'
        $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/attendance/leaves', [
                'worker_id' => $this->empWorker->id,
                'leave_type_name' => 'Paid Leave',
                'is_paid' => 1,
                'start_date' => '2026-10-30',
                'end_date' => '2026-10-30',
                'reason' => 'Medical appointment',
            ])->assertRedirect();

        $leave = LeaveRequest::where('worker_id', $this->empWorker->id)->first();
        $this->assertNotNull($leave);

        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/attendance/leaves/{$leave->id}/review", [
                'action' => 'approved',
            ])->assertRedirect();

        $this->assertDatabaseHas('daily_attendances', [
            'worker_id' => $this->empWorker->id,
            'status' => 'paid_leave',
        ]);

        // Export Payroll CSV and verify output
        $csvResponse = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/attendance/export-payroll?start_date=2026-10-01&end_date=2026-10-31');

        $csvResponse->assertStatus(200);
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('EMP-2026-0001', $csvContent);
        $this->assertStringContainsString('Vikram Shinde', $csvContent);
    }

    public function test_admin_date_wise_and_month_wise_attendance_register_views(): void
    {
        $date = '2026-10-28';

        // Mark EMP worker present (9 hrs -> 8 regular + 1 OT) and SUB worker half_day (4 hrs)
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteMumbai->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->empWorker->id,
            'attendance_date' => $date,
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
            'status' => 'present',
        ]);

        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteMumbai->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->subWorker->id,
            'attendance_date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '13:00:00',
            'status' => 'half_day',
        ]);

        // 1. Date-Wise View on /attendance/register
        $dateResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get("/attendance/register?mode=date&date={$date}");

        $dateResp->assertStatus(200);
        $dateResp->assertSee('Workforce Attendance & Present Register');
        $dateResp->assertSee('Date-Wise Worker Attendance Register');
        $dateResp->assertSee('Vikram Shinde');
        $dateResp->assertSee('Ganesh More');
        $dateResp->assertSee('Present (P)');
        $dateResp->assertSee('Half Day (HD)');

        // Filter by present only
        $presentOnlyResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get("/attendance/register?mode=date&date={$date}&presence_filter=present");

        $presentOnlyResp->assertStatus(200);
        $presentOnlyResp->assertSee('Vikram Shinde');
        $presentOnlyResp->assertDontSee('Ganesh More');

        // 2. Month-Wise Matrix View on /attendance/register and /reports/workforce
        $monthResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/attendance/register?mode=month&month=2026-10');

        $monthResp->assertStatus(200);
        $monthResp->assertSee('Month-Wise Attendance Register Matrix');
        $monthResp->assertSee('October 2026');
        $monthResp->assertSee('Vikram Shinde');
        $monthResp->assertSee('Ganesh More');
        $monthResp->assertSee('Daily Present Total (P+HD)');

        $reportMonthResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/reports/workforce?mode=month&month=2026-10');

        $reportMonthResp->assertStatus(200);
        $reportMonthResp->assertSee('Workforce Attendance');
        $reportMonthResp->assertSee('Month-Wise Attendance Register Matrix');
    }
}

