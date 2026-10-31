<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectMilestone;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase1ProjectsAndSitesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected User $adminUser;
    protected User $seniorManager;
    protected User $siteManagerA;
    protected User $supervisorA;
    protected Project $project1;
    protected Project $project2;
    protected Site $site1;
    protected Site $site2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'Apex Civil Infrastructure Ltd',
            'code' => 'APEX',
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyA->id);

        $roleAdmin = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $roleSenior = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Senior Manager',
            'slug' => 'senior_manager',
            'permissions' => ['projects.view', 'projects.create', 'projects.update', 'sites.view', 'sites.create', 'sites.update'],
        ]);

        $roleSiteMgr = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['projects.view', 'sites.view', 'workforce.view', 'workforce.create', 'workforce.update'],
        ]);

        $roleSupervisor = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['projects.view', 'sites.view', 'workforce.view'],
        ]);

        $this->adminUser = $this->makeUser('admin@apex.test', $roleAdmin);
        $this->seniorManager = $this->makeUser('senior@apex.test', $roleSenior);
        $this->siteManagerA = $this->makeUser('sm1@apex.test', $roleSiteMgr);
        $this->supervisorA = $this->makeUser('sup1@apex.test', $roleSupervisor);

        $this->project1 = Project::create([
            'company_id' => $this->companyA->id,
            'code' => 'PRJ-NH48',
            'name' => 'NH-48 Corridor Package 1',
            'client_name' => 'NHAI',
            'manager_id' => $this->seniorManager->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2027-06-30',
        ]);

        $this->project2 = Project::create([
            'company_id' => $this->companyA->id,
            'code' => 'PRJ-METRO',
            'name' => 'Metro Line 3 Viaduct',
            'client_name' => 'MMRCL',
            'manager_id' => $this->seniorManager->id,
            'status' => 'planning',
        ]);

        $this->site1 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project1->id,
            'code' => 'SITE-01',
            'name' => 'Sector 1 Flyover',
            'site_manager_id' => $this->siteManagerA->id,
            'supervisor_id' => $this->supervisorA->id,
            'latitude' => 18.520430,
            'longitude' => 73.856743,
            'geofence_radius_m' => 250,
            'shift_start' => '08:30:00',
            'shift_end' => '17:30:00',
            'status' => 'active',
        ]);

        $this->site2 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project2->id,
            'code' => 'SITE-02',
            'name' => 'Metro Depot Yard',
            'geofence_radius_m' => 300,
            'shift_start' => '09:00:00',
            'shift_end' => '18:00:00',
            'status' => 'active',
        ]);

        $this->project1->linkedSites()->attach($this->site1->id, [
            'company_id' => $this->companyA->id,
            'start_date' => '2026-01-01',
            'is_primary' => true,
        ]);

        $this->project2->linkedSites()->attach($this->site2->id, [
            'company_id' => $this->companyA->id,
            'start_date' => '2026-02-01',
            'is_primary' => true,
        ]);
    }

    protected function makeUser(string $email, Role $role): User
    {
        $user = User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->companies()->attach($this->companyA->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        return $user;
    }

    public function test_role_access_matrix_enforces_assigned_site_and_project_scope(): void
    {
        // Admin and Senior Manager can view both projects and both sites
        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('PRJ-NH48')
            ->assertSee('PRJ-METRO');

        $this->actingAs($this->seniorManager)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('sites.show', $this->site2))
            ->assertOk();

        // Supervisor A is assigned only to Site 1 (Project 1)
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('PRJ-NH48')
            ->assertDontSee('PRJ-METRO');

        // Supervisor A can view Project 1 and Site 1
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.show', $this->project1))
            ->assertOk();

        // Supervisor A gets 403 on unassigned Site 2 and Project 2
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('sites.show', $this->site2))
            ->assertForbidden();

        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.show', $this->project2))
            ->assertForbidden();
    }

    public function test_project_can_be_created_and_linked_to_multiple_sites(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('projects.store'), [
                'code' => 'PRJ-MULTI-01',
                'name' => 'Multi Corridor Ring Road',
                'client_name' => 'MSRDC',
                'manager_id' => $this->seniorManager->id,
                'status' => 'active',
                'start_date' => '2026-03-01',
                'end_date' => '2027-12-31',
                'description' => 'Spans Sector 1 and Depot Yard',
                'site_ids' => [$this->site1->id, $this->site2->id],
            ]);

        $project = Project::where('code', 'PRJ-MULTI-01')->firstOrFail();
        $response->assertRedirect(route('projects.show', $project));

        $this->assertCount(2, $project->linkedSites);
    }

    public function test_worker_transfer_between_sites_preserves_dated_assignment_history(): void
    {
        $worker = Worker::create([
            'company_id' => $this->companyA->id,
            'code' => 'WRK-HIST-01',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'type' => 'employee',
            'trade_designation' => 'Mason',
            'daily_rate' => 750,
            'assigned_site_id' => $this->site1->id,
            'joining_date' => '2026-01-10',
            'status' => 'active',
        ]);

        // Initial assignment on Site 1
        SiteAssignment::recordWorkerAssignment(
            $worker,
            $this->site1->id,
            '2026-01-10',
            $this->adminUser->id,
            'Initial deployment to Site 1'
        );

        $this->assertDatabaseHas('site_assignments', [
            'worker_id' => $worker->id,
            'site_id' => $this->site1->id,
            'is_active' => true,
            'end_date' => null,
        ]);

        // Transfer worker to Site 2 via SiteController::storeAssignment
        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->from(route('sites.show', $this->site2))
            ->post(route('sites.assignments.store', $this->site2), [
                'assignment_type' => 'worker',
                'worker_id' => $worker->id,
                'role_on_site' => 'Senior Mason',
                'start_date' => '2026-04-01',
                'notes' => 'Transferred to Metro Depot Yard',
            ])
            ->assertRedirect(route('sites.show', $this->site2));

        // Verify previous assignment on Site 1 is closed (is_active = false, end_date set)
        $oldAssignment = SiteAssignment::where('worker_id', $worker->id)
            ->where('site_id', $this->site1->id)
            ->firstOrFail();

        $this->assertFalse($oldAssignment->is_active);
        $this->assertNotNull($oldAssignment->end_date);

        // Verify new assignment on Site 2 is active and worker's assigned_site_id updated
        $newAssignment = SiteAssignment::where('worker_id', $worker->id)
            ->where('site_id', $this->site2->id)
            ->firstOrFail();

        $this->assertTrue($newAssignment->is_active);
        $this->assertNull($newAssignment->end_date);
        $this->assertEquals($this->site2->id, $worker->fresh()->assigned_site_id);
    }

    public function test_project_milestones_and_versioned_documents_workflow(): void
    {
        // Add Milestone
        $this->actingAs($this->siteManagerA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('projects.milestones.store', $this->project1), [
                'site_id' => $this->site1->id,
                'title' => 'Pier P1 Pile Cap Casting',
                'due_date' => '2026-05-15',
                'status' => 'in_progress',
                'percent_complete' => 45,
            ])
            ->assertRedirect(route('projects.show', ['project' => $this->project1, 'tab' => 'milestones']));

        $milestone = ProjectMilestone::where('project_id', $this->project1->id)->firstOrFail();
        $this->assertEquals(45, $milestone->percent_complete);
        $this->assertEquals(45, $this->project1->fresh()->overallProgressPercent());

        // Upload Document v1
        $fileV1 = UploadedFile::fake()->create('flyover_gad.pdf', 120, 'application/pdf');

        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('projects.documents.store', $this->project1), [
                'site_id' => $this->site1->id,
                'document_type' => 'drawing',
                'title' => 'Flyover GAD Drawing',
                'document' => $fileV1,
            ])
            ->assertRedirect(route('projects.show', ['project' => $this->project1, 'tab' => 'documents']));

        // Upload Document v2 with same title & type -> auto-increments version to 2
        $fileV2 = UploadedFile::fake()->create('flyover_gad_rev2.pdf', 150, 'application/pdf');

        $this->actingAs($this->adminUser)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('projects.documents.store', $this->project1), [
                'site_id' => $this->site1->id,
                'document_type' => 'drawing',
                'title' => 'Flyover GAD Drawing',
                'document' => $fileV2,
            ])
            ->assertRedirect(route('projects.show', ['project' => $this->project1, 'tab' => 'documents']));

        $docs = ProjectDocument::where('project_id', $this->project1->id)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $docs);
        $this->assertEquals(1, $docs[0]->version);
        $this->assertEquals(2, $docs[1]->version);
        Storage::disk('local')->assertExists($docs[1]->file_path);

        // Authorized download works
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->get(route('projects.documents.download', [$this->project1, $docs[1]]))
            ->assertOk();
    }

    public function test_site_geofence_haversine_distance_calculation(): void
    {
        // Point ~50m away from Site 1 (18.520430, 73.856743) with 250m radius
        $this->assertTrue($this->site1->isWithinGeofence(18.520650, 73.856900));

        // Point ~2.5km away from Site 1 -> outside 250m geofence
        $this->assertFalse($this->site1->isWithinGeofence(18.540000, 73.870000));
    }

    public function test_topbar_site_switcher_sets_active_site_in_session(): void
    {
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('switch_site'), ['site_id' => $this->site1->id])
            ->assertSessionHas('active_site_id', $this->site1->id);

        // Supervisor A cannot switch to unassigned Site 2
        $this->actingAs($this->supervisorA)
            ->withSession(['active_company_id' => $this->companyA->id])
            ->post(route('switch_site'), ['site_id' => $this->site2->id])
            ->assertForbidden();
    }
}
