<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceAndConflictTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected Site $site1;
    protected Site $site2;
    protected User $supervisor;
    protected Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'NSD Co', 'code' => 'NSD', 'is_active' => true]);
        TenantContext::setCompanyId($this->company->id);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.record', 'attendance.view'],
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'name' => 'NH-48',
            'code' => 'NH-48',
            'status' => 'active',
        ]);

        $this->site1 = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Sector 1',
            'code' => 'SITE-1',
            'status' => 'active',
        ]);

        $this->site2 = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Sector 2',
            'code' => 'SITE-2',
            'status' => 'active',
        ]);

        $this->supervisor = User::create([
            'name' => 'Ramesh Pawar',
            'email' => 'ramesh@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->supervisor->companies()->attach($this->company->id, ['role_id' => $role->id]);
        $this->site1->assignedUsers()->attach($this->supervisor->id);
        $this->site2->assignedUsers()->attach($this->supervisor->id);

        $this->worker = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'first_name' => 'Anand',
            'last_name' => 'Kulkarni',
            'code' => 'W-EMP-001',
            'trade_designation' => 'Excavator Operator',
            'daily_rate' => 1200,
            'assigned_site_id' => $this->site1->id,
        ]);
    }

    public function test_worker_can_attend_multiple_sites_on_same_day_without_conflict(): void
    {
        $service = new AttendanceService();
        $date = '2026-10-28';

        // Morning shift at Site 1 (07:30 - 12:30 = 5.0 hours)
        $service->recordMusterRoll($this->site1->id, $date, [
            $this->worker->id => [
                'status' => 'present',
                'clock_in' => '07:30',
                'clock_out' => '12:30',
            ],
        ], $this->supervisor->id);

        // Afternoon shift at Site 2 (14:00 - 18:30 = 4.5 hours)
        $service->recordMusterRoll($this->site2->id, $date, [
            $this->worker->id => [
                'status' => 'present',
                'clock_in' => '14:00',
                'clock_out' => '18:30',
            ],
        ], $this->supervisor->id);

        $records = DailyAttendance::where('worker_id', $this->worker->id)->whereDate('attendance_date', $date)->get();
        $this->assertCount(2, $records);
        $this->assertFalse($records[0]->has_conflict);
        $this->assertFalse($records[1]->has_conflict);
        $this->assertEquals(5.00, $records[0]->worked_hours);
        $this->assertEquals(4.50, $records[1]->worked_hours);
    }

    public function test_overlapping_shifts_trigger_conflict_warning_on_both_records(): void
    {
        $service = new AttendanceService();
        $date = '2026-10-28';

        // Shift 1 at Site 1: 08:00 - 12:00
        $service->recordMusterRoll($this->site1->id, $date, [
            $this->worker->id => [
                'status' => 'present',
                'clock_in' => '08:00',
                'clock_out' => '12:00',
            ],
        ], $this->supervisor->id);

        // Shift 2 at Site 2 overlapping: 11:00 - 15:00
        $service->recordMusterRoll($this->site2->id, $date, [
            $this->worker->id => [
                'status' => 'present',
                'clock_in' => '11:00',
                'clock_out' => '15:00',
            ],
        ], $this->supervisor->id);

        $records = DailyAttendance::where('worker_id', $this->worker->id)->whereDate('attendance_date', $date)->get();
        $this->assertCount(2, $records);
        $this->assertTrue($records[0]->has_conflict, 'First record must be flagged with conflict');
        $this->assertTrue($records[1]->has_conflict, 'Second record must be flagged with conflict');
        $this->assertNotNull($records[0]->conflict_reason);
        $this->assertNotNull($records[1]->conflict_reason);
    }

    public function test_conflict_is_automatically_cleared_when_overlap_is_resolved(): void
    {
        $date = '2026-10-28';

        $att1 = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site1->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'attendance_date' => $date,
            'clock_in' => '08:00:00',
            'clock_out' => '12:00:00',
            'status' => 'present',
        ]);

        $att2 = DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site2->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'attendance_date' => $date,
            'clock_in' => '11:00:00',
            'clock_out' => '15:00:00',
            'status' => 'present',
        ]);

        $att1->refresh();
        $att2->refresh();
        $this->assertTrue($att1->has_conflict);
        $this->assertTrue($att2->has_conflict);

        // Correct att2 to non-overlapping hours (13:00 - 17:00)
        $att2->clock_in = '13:00:00';
        $att2->clock_out = '17:00:00';
        $att2->save();

        $att1->refresh();
        $att2->refresh();
        $this->assertFalse($att1->has_conflict, 'Conflict on att1 must clear when overlap is resolved');
        $this->assertFalse($att2->has_conflict, 'Conflict on att2 must clear when overlap is resolved');
    }

    public function test_unverified_hours_are_not_fabricated_with_default_times(): void
    {
        $service = new AttendanceService();
        $date = '2026-10-28';

        // Worker marked present without specific time punches
        $service->recordMusterRoll($this->site1->id, $date, [
            $this->worker->id => [
                'status' => 'present',
                'clock_in' => null,
                'clock_out' => null,
            ],
        ], $this->supervisor->id);

        $attendance = DailyAttendance::where('worker_id', $this->worker->id)->first();
        $this->assertNull($attendance->clock_in, 'Clock in must remain null if not entered');
        $this->assertNull($attendance->clock_out, 'Clock out must remain null if not entered');
        $this->assertEquals(0.00, $attendance->worked_hours, 'Worked hours must be 0 when unverified');
    }

    public function test_payroll_aggregation_does_not_double_count_workers_attending_multiple_sites(): void
    {
        $service = new AttendanceService();
        $date = '2026-10-28';

        // Single worker attending Site 1 in morning (4 hours)
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site1->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'attendance_date' => $date,
            'clock_in' => '08:00:00',
            'clock_out' => '12:00:00',
            'worked_hours' => 4.00,
            'status' => 'present',
        ]);

        // Same worker attending Site 2 in afternoon (4 hours)
        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->site2->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'attendance_date' => $date,
            'clock_in' => '13:00:00',
            'clock_out' => '17:00:00',
            'worked_hours' => 4.00,
            'status' => 'present',
        ]);

        $summary = $service->getDailyAttendanceAggregation($date);

        // CRITICAL: Distinct headcount MUST be 1, never 2!
        $this->assertEquals(1, $summary['total_headcount'], 'Worker attending two sites must not be double counted');
        $this->assertEquals(8.00, $summary['total_hours'], 'Total hours across both sites must be aggregated');
    }
}
