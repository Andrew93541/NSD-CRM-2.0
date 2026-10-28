<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenancySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_fail_closed_when_tenant_context_is_not_set(): void
    {
        TenantContext::clear();

        $company = Company::create(['name' => 'Company A', 'code' => 'CMPA', 'is_active' => true]);

        // Direct DB insert to simulate data in database
        TenantContext::setCompanyId($company->id);
        $worker = Worker::create([
            'company_id' => $company->id,
            'worker_type' => 'employee',
            'first_name' => 'Ramesh',
            'last_name' => 'Kumar',
            'code' => 'W-001',
            'daily_rate' => 800,
        ]);

        // Now clear tenant context
        TenantContext::clear();

        // Model query must fail-closed and return 0 results
        $this->assertEquals(0, Worker::count());
    }

    public function test_tenant_data_is_strictly_isolated_between_companies(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'code' => 'CMPA', 'is_active' => true]);
        $companyB = Company::create(['name' => 'Company B', 'code' => 'CMPB', 'is_active' => true]);

        // Create worker in Company A
        TenantContext::setCompanyId($companyA->id);
        $workerA = Worker::create([
            'company_id' => $companyA->id,
            'worker_type' => 'employee',
            'first_name' => 'Worker',
            'last_name' => 'A',
            'code' => 'W-A',
            'daily_rate' => 800,
        ]);

        // Create worker in Company B
        TenantContext::setCompanyId($companyB->id);
        $workerB = Worker::create([
            'company_id' => $companyB->id,
            'worker_type' => 'employee',
            'first_name' => 'Worker',
            'last_name' => 'B',
            'code' => 'W-B',
            'daily_rate' => 900,
        ]);

        // Querying under Company A must ONLY return Company A worker
        TenantContext::setCompanyId($companyA->id);
        $workersInA = Worker::all();
        $this->assertCount(1, $workersInA);
        $this->assertEquals('W-A', $workersInA->first()->code);

        // Querying under Company B must ONLY return Company B worker
        TenantContext::setCompanyId($companyB->id);
        $workersInB = Worker::all();
        $this->assertCount(1, $workersInB);
        $this->assertEquals('W-B', $workersInB->first()->code);
    }

    public function test_supervisor_cannot_record_attendance_for_unassigned_site(): void
    {
        $company = Company::create(['name' => 'NSD Co', 'code' => 'NSD', 'is_active' => true]);
        TenantContext::setCompanyId($company->id);

        $roleSupervisor = Role::create([
            'company_id' => $company->id,
            'name' => 'Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.record', 'attendance.view'],
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Project 1',
            'code' => 'P1',
            'status' => 'active',
        ]);

        $siteAssigned = Site::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Assigned Site',
            'code' => 'SITE-1',
            'status' => 'active',
        ]);

        $siteUnassigned = Site::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Unassigned Site',
            'code' => 'SITE-2',
            'status' => 'active',
        ]);

        $supervisor = User::create([
            'name' => 'Supervisor Sam',
            'email' => 'sam@nsd.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $supervisor->companies()->attach($company->id, ['role_id' => $roleSupervisor->id]);
        $siteAssigned->assignedUsers()->attach($supervisor->id);

        $worker = Worker::create([
            'company_id' => $company->id,
            'worker_type' => 'employee',
            'first_name' => 'Worker',
            'last_name' => 'One',
            'code' => 'W-1',
            'daily_rate' => 800,
            'assigned_site_id' => $siteUnassigned->id,
        ]);

        // Attempting to record muster for unassigned site must return 403 Forbidden
        $response = $this->actingAs($supervisor)->post('/attendance/muster', [
            'site_id' => $siteUnassigned->id,
            'attendance_date' => '2026-10-28',
            'workers' => [
                $worker->id => [
                    ['status' => 'present', 'clock_in' => '08:00', 'clock_out' => '17:00'],
                ],
            ],
        ]);

        $response->assertStatus(403);
    }
}
