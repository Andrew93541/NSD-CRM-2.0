<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CrmNotification;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteNote;
use App\Models\Task;
use App\Models\User;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4TasksNotesNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Project $project;
    protected Site $siteMumbai;
    protected Site $sitePune;
    protected User $siteManager;
    protected User $mumbaiSupervisor;
    protected User $puneSupervisor;

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
            'name' => 'Coastal Road Pkg 4',
            'code' => 'CRP-04',
            'status' => 'active',
        ]);

        $this->siteMumbai = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Worli Seaface Yard',
            'code' => 'SITE-WOR',
            'status' => 'active',
        ]);

        $this->sitePune = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'name' => 'Baner Casting Yard',
            'code' => 'SITE-BAN',
            'status' => 'active',
        ]);

        $this->siteManager = $this->makeUser('Site Manager', 'sitemgr@nsd.local', 'site_manager');
        $this->mumbaiSupervisor = $this->makeUser('Mumbai Supervisor', 'mumsup@nsd.local', 'site_supervisor');
        $this->puneSupervisor = $this->makeUser('Pune Supervisor', 'punesup@nsd.local', 'site_supervisor');

        $this->siteMumbai->assignedUsers()->attach([$this->siteManager->id, $this->mumbaiSupervisor->id]);
        $this->sitePune->assignedUsers()->attach([$this->puneSupervisor->id]);
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

    public function test_task_creation_generates_code_logs_activity_and_sends_notification(): void
    {
        $year = now()->format('Y');

        $response = $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/tasks', [
                'site_id' => $this->siteMumbai->id,
                'title' => 'Verify Pile Cap Concrete Slump Test',
                'description' => 'Check slump cone test for batch #14 before pouring.',
                'priority' => 'critical',
                'due_date' => now()->addDays(2)->format('Y-m-d'),
                'assigned_to' => $this->mumbaiSupervisor->id,
            ]);

        $response->assertRedirect();

        $task = Task::where('title', 'Verify Pile Cap Concrete Slump Test')->first();
        $this->assertNotNull($task);
        $this->assertEquals("TSK-{$year}-0001", $task->code);
        $this->assertEquals($this->project->id, $task->project_id);
        $this->assertEquals($this->siteMumbai->id, $task->site_id);

        // Append-only activity log created
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $this->company->id,
            'site_id' => $this->siteMumbai->id,
            'action' => 'task.created',
            'subject_id' => $task->id,
        ]);

        // In-app notification created for assigned supervisor
        $this->assertDatabaseHas('crm_notifications', [
            'company_id' => $this->company->id,
            'user_id' => $this->mumbaiSupervisor->id,
            'type' => 'task_assigned',
        ]);

        // Kanban board & list views render the task for Mumbai supervisor
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/tasks?view=board')
            ->assertStatus(200)
            ->assertSee($task->code)
            ->assertSee('Verify Pile Cap Concrete Slump Test');

        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->get('/tasks?view=list')
            ->assertStatus(200)
            ->assertSee($task->code);
    }

    public function test_site_isolation_prevents_unassigned_supervisor_from_viewing_or_creating_tasks(): void
    {
        $task = Task::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteMumbai->id,
            'code' => 'TSK-2026-0099',
            'title' => 'Mumbai Exclusive Task',
            'priority' => 'high',
            'status' => 'todo',
            'created_by' => $this->siteManager->id,
        ]);

        // Pune supervisor cannot view Mumbai task detail (403)
        $this->actingAs($this->puneSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->get("/tasks/{$task->id}")
            ->assertStatus(403);

        // Pune supervisor cannot create a task on Mumbai site (403)
        $this->actingAs($this->puneSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/tasks', [
                'site_id' => $this->siteMumbai->id,
                'title' => 'Unauthorized Task',
                'priority' => 'low',
            ])
            ->assertStatus(403);
    }

    public function test_task_status_progression_comments_site_notes_and_notifications_read_flow(): void
    {
        $task = Task::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteMumbai->id,
            'code' => 'TSK-2026-0005',
            'title' => 'Install Barricades Along Excavation Trench',
            'priority' => 'high',
            'status' => 'todo',
            'assigned_to' => $this->mumbaiSupervisor->id,
            'created_by' => $this->siteManager->id,
        ]);

        // 1. Assigned supervisor updates status to completed
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", [
                'status' => 'completed',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertNotNull($task->completed_at);

        // Creator (siteManager) receives task_status notification
        $this->assertDatabaseHas('crm_notifications', [
            'user_id' => $this->siteManager->id,
            'type' => 'task_status',
        ]);

        // 2. Assigned supervisor posts a comment
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/tasks/{$task->id}/comments", [
                'comment' => 'All 45m of reflective barricades installed and inspected.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $this->mumbaiSupervisor->id,
            'comment' => 'All 45m of reflective barricades installed and inspected.',
        ]);

        // 3. Log a pinned Site Field Note
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post('/tasks/notes', [
                'site_id' => $this->siteMumbai->id,
                'category' => 'safety_hse',
                'title' => 'Monsoon Dewatering Pump Check',
                'body' => 'Two 10HP diesel pumps tested and on standby.',
                'is_pinned' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('site_notes', [
            'site_id' => $this->siteMumbai->id,
            'category' => 'safety_hse',
            'title' => 'Monsoon Dewatering Pump Check',
            'is_pinned' => 1,
        ]);

        // 4. Mark notification as read (and verify another user cannot mark it read)
        $notif = CrmNotification::where('user_id', $this->siteManager->id)->first();
        $this->assertNotNull($notif);

        $this->actingAs($this->puneSupervisor)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/tasks/notifications/{$notif->id}/read")
            ->assertStatus(403);

        $this->actingAs($this->siteManager)
            ->withSession(['active_company_id' => $this->company->id])
            ->post("/tasks/notifications/{$notif->id}/read")
            ->assertRedirect();

        $notif->refresh();
        $this->assertNotNull($notif->read_at);
    }
}

