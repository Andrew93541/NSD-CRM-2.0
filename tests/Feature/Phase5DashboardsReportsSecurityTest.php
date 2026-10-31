<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;
use Tests\TestCase;

class Phase5DashboardsReportsSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Company $otherCompany;
    protected Project $project;
    protected Site $siteMumbai;
    protected Site $sitePune;
    protected User $admin;
    protected User $siteManager;
    protected User $supervisor;
    protected Worker $workerMumbai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction',
            'code' => 'NSD-HQ',
            'is_active' => true,
        ]);

        $this->otherCompany = Company::create([
            'name' => 'Other Infra Ltd',
            'code' => 'OTH-01',
            'is_active' => true,
        ]);

        TenantContext::setCompanyId($this->company->id);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-P5-01',
            'name' => 'Samruddhi Highway Package 9',
            'status' => 'active',
        ]);

        $this->siteMumbai = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-MUM-01',
            'name' => 'Mumbai Coastal Yard',
            'location' => 'Worli',
            'city' => 'Mumbai',
            'status' => 'active',
        ]);

        $this->sitePune = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'SITE-PUN-02',
            'name' => 'Pune Ring Road Camp',
            'location' => 'Hinjewadi',
            'city' => 'Pune',
            'status' => 'active',
        ]);

        $this->admin = $this->makeUser('Admin User', 'admin@nsd.local', 'company_admin');
        $this->siteManager = $this->makeUser('Site Manager', 'sitemgr@nsd.local', 'site_manager');
        $this->supervisor = $this->makeUser('Supervisor', 'sup@nsd.local', 'site_supervisor');

        // Supervisor is assigned ONLY to Mumbai site
        $this->siteMumbai->assignedUsers()->attach([$this->siteManager->id, $this->supervisor->id]);
        $this->sitePune->assignedUsers()->attach([$this->siteManager->id]);

        $this->workerMumbai = Worker::create([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => 'EMP-2026-0099',
            'first_name' => 'Suresh',
            'last_name' => 'Kulkarni',
            'trade_designation' => 'Site Engineer',
            'assigned_site_id' => $this->siteMumbai->id,
            'daily_rate' => 1100,
            'overtime_rate' => 175,
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

    public function test_role_scoped_dashboard_and_multi_site_comparison_report(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        DailyAttendance::create([
            'company_id' => $this->company->id,
            'site_id' => $this->siteMumbai->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->workerMumbai->id,
            'attendance_date' => $today,
            'clock_in' => '08:00:00',
            'clock_out' => '18:00:00',
            'status' => 'present',
        ]);

        Task::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteMumbai->id,
            'code' => 'TSK-2026-0001',
            'title' => 'Inspect Pier P14 Formwork',
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => Carbon::yesterday()->format('Y-m-d'),
            'created_by' => $this->admin->id,
        ]);

        // 1. Admin Dashboard renders Compliance & Field Alerts + Recent Append-Only Audit Activity
        $dashResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/dashboard');

        $dashResp->assertStatus(200);
        $dashResp->assertSee('Compliance & Field Alerts');
        $dashResp->assertSee('Overdue Tasks');
        $dashResp->assertSee('Recent Append-Only Audit Activity');

        // 2. Multi-Site Comparison Report renders both Mumbai and Pune sites for Admin
        $multiResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/reports/multi-site');

        $multiResp->assertStatus(200);
        $multiResp->assertSee('Multi-Site Operations & Attendance Comparison');
        $multiResp->assertSee('Mumbai Coastal Yard');
        $multiResp->assertSee('Pune Ring Road Camp');

        // 3. Supervisor only sees their assigned Mumbai site on Multi-Site Comparison Report
        $supMultiResp = $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/reports/multi-site');

        $supMultiResp->assertStatus(200);
        $supMultiResp->assertSee('Mumbai Coastal Yard');
        $supMultiResp->assertDontSee('Pune Ring Road Camp');
    }

    public function test_audit_log_viewer_csv_export_and_append_only_immutability(): void
    {
        $log = AuditLog::record(
            'security.test_event',
            $this->workerMumbai,
            null,
            ['note' => 'Verified immutable audit record'],
            $this->siteMumbai->id,
            $this->admin->id,
            $this->company->id
        );

        $this->assertNotNull($log);

        // 1. Supervisor cannot view global security audit logs (403)
        $this->actingAs($this->supervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/reports/audit-logs')
            ->assertStatus(403);

        // 2. Admin can view audit logs and filter by event
        $auditResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/reports/audit-logs?event=security.test_event');

        $auditResp->assertStatus(200);
        $auditResp->assertSee('Append-Only Security & Operational Audit Trail');
        $auditResp->assertSee('security.test_event');

        // 3. Admin can export Audit Logs CSV
        $csvResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/reports/audit-logs/export');

        $csvResp->assertStatus(200);
        $this->assertStringContainsString('security.test_event', $csvResp->streamedContent());

        // 4. ActivityLog is strictly append-only (cannot update or delete)
        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_staff_login_rate_limiting_and_audit_trail(): void
    {
        RateLimiter::clear('staff-login:admin@nsd.local|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin@nsd.local',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        // 6th attempt is throttled
        $throttled = $this->post('/login', [
            'email' => 'admin@nsd.local',
            'password' => 'wrong-password',
        ]);
        $throttled->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('email')
        );

        // Audit log recorded login failures
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_bulk_worker_csv_upload_system_and_template_download(): void
    {
        // 1. Render bulk CSV import page
        $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/workforce/import')
            ->assertStatus(200)
            ->assertSee('Bulk Worker CSV Upload');

        // 2. Download sample CSV template
        $tplResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/workforce/import/template');

        $tplResp->assertStatus(200);
        $this->assertStringContainsString('worker_type,first_name,last_name,trade_designation', $tplResp->streamedContent());

        // 3. Upload a multi-row CSV with Direct Employee, Direct Labour, and Subcontractor Worker
        $csvContent = implode("\n", [
            'worker_type,first_name,last_name,trade_designation,skill_level,department,daily_rate,overtime_rate,site_code,subcontractor_name,phone,aadhaar_last_four,joining_date',
            'employee,Anil,Kadam,Quantity Surveyor,highly_skilled,Civil & Earthworks,1500,250,SITE-MUM-01,,9811112222,1234,2026-10-01',
            'direct_labour,Sunil,Mane,Concrete Mason,skilled,Structural & Masonry,900,140,SITE-MUM-01,,9811113333,5678,2026-10-02',
            'subcontractor_worker,Prakash,Salvi,Steel Fitter,semi_skilled,Structural & Masonry,800,120,SITE-PUN-02,Sahyadri Steel Works,9811114444,9012,2026-10-03',
        ]);

        $csvFile = UploadedFile::fake()->createWithContent('workers_batch.csv', $csvContent);

        $importResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/workforce/import', [
                'csv_file' => $csvFile,
            ]);

        $importResp->assertRedirect(route('workforce.index'));

        $this->assertDatabaseHas('workers', [
            'company_id' => $this->company->id,
            'first_name' => 'Anil',
            'last_name' => 'Kadam',
            'worker_type' => 'employee',
            'assigned_site_id' => $this->siteMumbai->id,
            'daily_rate' => 1500,
        ]);

        $this->assertDatabaseHas('workers', [
            'company_id' => $this->company->id,
            'first_name' => 'Sunil',
            'last_name' => 'Mane',
            'worker_type' => 'direct_labour',
            'assigned_site_id' => $this->siteMumbai->id,
        ]);

        $this->assertDatabaseHas('workers', [
            'company_id' => $this->company->id,
            'first_name' => 'Prakash',
            'last_name' => 'Salvi',
            'worker_type' => 'subcontractor_worker',
            'assigned_site_id' => $this->sitePune->id,
        ]);

        $this->assertDatabaseHas('subcontractors', [
            'company_id' => $this->company->id,
            'name' => 'Sahyadri Steel Works',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'worker.bulk_csv_imported',
        ]);

        // 4. Re-uploading the exact same CSV is blocked by Duplicate Validation (existing mobile/worker check)
        $duplicateFile = UploadedFile::fake()->createWithContent('workers_duplicate.csv', $csvContent);
        $dupResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/workforce/import', [
                'csv_file' => $duplicateFile,
            ]);

        $dupResp->assertSessionHasErrors();

        // 5. Uploading a CSV containing internal duplicate rows (same mobile or same name+aadhaar in one file) is blocked
        $inFileDupCsv = implode("\n", [
            'worker_type,first_name,last_name,trade_designation,daily_rate,phone,aadhaar_last_four',
            'employee,Kiran,Patil,Surveyor,1200,9899990001,4321',
            'direct_labour,Kiran,Patil,Helper,700,9899990001,4321',
        ]);
        $inFileDupUpload = UploadedFile::fake()->createWithContent('in_file_dup.csv', $inFileDupCsv);
        $inFileResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/workforce/import', [
                'csv_file' => $inFileDupUpload,
            ]);

        $inFileResp->assertSessionHasErrors();
        $this->assertDatabaseMissing('workers', [
            'first_name' => 'Kiran',
            'last_name' => 'Patil',
        ]);

        // 6. Workers with the SAME Full Name, Date of Birth, and Address ARE allowed when unique identifiers (phone/email/bank) are distinct
        $sameNameDobAddressCsv = implode("\n", [
            'worker_type,first_name,last_name,trade_designation,daily_rate,phone,date_of_birth,address',
            'direct_labour,Suresh,Jadhav,Mason,900,9855551001,1992-05-10,Chakan Labour Camp Block A',
            'direct_labour,Suresh,Jadhav,Helper,700,9855551002,1992-05-10,Chakan Labour Camp Block A',
        ]);
        $sameNameUpload = UploadedFile::fake()->createWithContent('same_name_dob_address.csv', $sameNameDobAddressCsv);
        $sameNameResp = $this->actingAs($this->admin)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/workforce/import', [
                'csv_file' => $sameNameUpload,
            ]);

        $sameNameResp->assertRedirect(route('workforce.index'));
        $this->assertSame(
            2,
            Worker::where('company_id', $this->company->id)
                ->where('first_name', 'Suresh')
                ->where('last_name', 'Jadhav')
                ->whereDate('date_of_birth', '1992-05-10')
                ->where('address', 'Chakan Labour Camp Block A')
                ->count()
        );
    }
}
