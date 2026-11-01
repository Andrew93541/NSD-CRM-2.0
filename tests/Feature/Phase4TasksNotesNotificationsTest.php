<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CrmNotification;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\SiteNote;
use App\Models\Task;
use App\Models\User;
use App\Models\Worker;
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

    protected function makeUser(string $name, string $email, string $roleSlug, ?Company $company = null): User
    {
        $targetCompany = $company ?? $this->company;

        $role = Role::firstOrCreate(
            ['company_id' => $targetCompany->id, 'slug' => $roleSlug],
            ['name' => ucfirst(str_replace('_', ' ', $roleSlug)), 'permissions' => ['*']]
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->companies()->attach($targetCompany->id, [
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_task_creation_generates_code_supports_worker_assignment_logs_activity_and_sends_notification(): void
    {
        $year = now()->format('Y');

        $worker = Worker::create([
            'company_id' => $this->company->id,
            'assigned_site_id' => $this->siteMumbai->id,
            'code' => 'EMP-2026-0001',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'category' => 'direct_employee',
            'skill_category' => 'Barbender',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/tasks', [
                'site_id' => $this->siteMumbai->id,
                'title' => 'Verify Pile Cap Concrete Slump Test',
                'description' => 'Check slump cone test for batch #14 before pouring.',
                'priority' => 'critical',
                'due_date' => now()->addDays(2)->format('Y-m-d'),
                'assigned_to' => $this->mumbaiSupervisor->id,
                'assigned_worker_id' => $worker->id,
            ]);

        $response->assertRedirect();

        $task = Task::where('title', 'Verify Pile Cap Concrete Slump Test')->first();
        $this->assertNotNull($task);
        $this->assertEquals("TSK-{$year}-0001", $task->code);
        $this->assertEquals($this->project->id, $task->project_id);
        $this->assertEquals($this->siteMumbai->id, $task->site_id);
        $this->assertEquals($worker->id, $task->assigned_worker_id);

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

        // Kanban board, list, and detail views render the task and assigned worker
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/tasks?view=board')
            ->assertStatus(200)
            ->assertSee($task->code)
            ->assertSee('Verify Pile Cap Concrete Slump Test')
            ->assertSee('EMP-2026-0001');

        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/tasks?view=list')
            ->assertStatus(200)
            ->assertSee($task->code)
            ->assertSee('EMP-2026-0001');

        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertSee('EMP-2026-0001');
    }

    public function test_supervisor_assigned_via_site_assignments_or_supervisor_id_can_access_site_dropdown_and_tasks(): void
    {
        $datedSupervisor = $this->makeUser('Dated Assignment Supervisor', 'datedsup@nsd.local', 'site_supervisor');

        // Assigned via SiteAssignment table (not user_site pivot)
        SiteAssignment::create([
            'company_id' => $this->company->id,
            'site_id' => $this->sitePune->id,
            'user_id' => $datedSupervisor->id,
            'role_in_site' => 'supervisor',
            'start_date' => now()->subDays(5)->format('Y-m-d'),
            'is_active' => true,
        ]);

        // Site dropdown in /tasks includes SITE-BAN (sitePune)
        $this->actingAs($datedSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/tasks')
            ->assertStatus(200)
            ->assertSee('SITE-BAN');

        // Can create a task for SITE-BAN
        $this->actingAs($datedSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/tasks', [
                'site_id' => $this->sitePune->id,
                'title' => 'Check Batching Plant Calibration',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'site_id' => $this->sitePune->id,
            'title' => 'Check Batching Plant Calibration',
        ]);
    }

    public function test_office_staff_can_view_tasks_and_detail_but_cannot_create_modify_or_delete_tasks_or_notes(): void
    {
        $officeStaff = $this->makeUser('Office Compliance', 'office@nsd.local', 'office_staff');

        $task = Task::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteMumbai->id,
            'code' => 'TSK-2026-0010',
            'title' => 'Submit Pour Card Documentation',
            'priority' => 'medium',
            'status' => 'todo',
            'created_by' => $this->siteManager->id,
        ]);

        $note = SiteNote::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'site_id' => $this->siteMumbai->id,
            'user_id' => $this->siteManager->id,
            'category' => 'quality',
            'title' => 'Cube Test Register Verified',
            'body' => '7-day cube strength exceeds 32 MPa.',
            'is_pinned' => false,
        ]);

        // Office staff CAN view /tasks board, list, notes tab, and /tasks/{task} detail
        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/tasks')
            ->assertStatus(200)
            ->assertSee('TSK-2026-0010');

        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/tasks/{$task->id}")
            ->assertStatus(200)
            ->assertSee('Submit Pour Card Documentation');

        // Office staff CANNOT create task, update status, comment, delete task, create note, pin note, or convert note
        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/tasks', [
                'site_id' => $this->siteMumbai->id,
                'title' => 'Forbidden Task',
                'priority' => 'low',
            ])
            ->assertStatus(403);

        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertStatus(403);

        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/tasks/{$task->id}")
            ->assertStatus(403);

        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/tasks/notes', [
                'site_id' => $this->siteMumbai->id,
                'category' => 'general',
                'title' => 'Forbidden Note',
                'body' => 'Not allowed',
            ])
            ->assertStatus(403);

        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/tasks/notes/{$note->id}/toggle-pin")
            ->assertStatus(403);

        $this->actingAs($officeStaff)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/tasks/notes/{$note->id}/convert-to-task")
            ->assertStatus(403);
    }

    public function test_site_and_cross_company_isolation_for_tasks_and_notes(): void
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

        // Pune supervisor cannot view, update, or create on Mumbai site (403)
        $this->actingAs($this->puneSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->get("/tasks/{$task->id}")
            ->assertStatus(403);

        $this->actingAs($this->puneSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertStatus(403);

        $this->actingAs($this->puneSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/tasks', [
                'site_id' => $this->siteMumbai->id,
                'title' => 'Unauthorized Task',
                'priority' => 'low',
            ])
            ->assertStatus(403);

        // Cross-company user cannot view or mutate Company A's tasks
        $otherCompany = Company::create(['name' => 'External Infra', 'code' => 'EXT', 'is_active' => true]);
        $externalAdmin = $this->makeUser('Ext Admin', 'admin@ext.local', 'company_admin', $otherCompany);

        $this->actingAs($externalAdmin)
            ->withSession(['current_company_id' => $otherCompany->id])
            ->get("/tasks/{$task->id}")
            ->assertStatus(404);
    }

    public function test_all_valid_and_invalid_status_transitions_full_editing_and_deletion(): void
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

        // Invalid transition: todo -> completed (must be rejected)
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->assertEquals('todo', $task->fresh()->status);

        // Invalid transition: todo -> review (must be rejected)
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'review'])
            ->assertSessionHasErrors('status');

        // 1. Valid transition: todo -> in_progress
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertEquals('in_progress', $task->fresh()->status);

        // 2. Valid transition: in_progress -> blocked
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'blocked'])
            ->assertSessionHasNoErrors();
        $this->assertEquals('blocked', $task->fresh()->status);

        // Invalid transition: blocked -> completed (must be rejected)
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        // 3. Valid transition: blocked -> in_progress
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertEquals('in_progress', $task->fresh()->status);

        // 4. Valid transition: in_progress -> review
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'review'])
            ->assertSessionHasNoErrors();
        $this->assertEquals('review', $task->fresh()->status);

        // 5. Valid transition: review -> in_progress (rework)
        $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertEquals('in_progress', $task->fresh()->status);

        // 6. Valid transition: in_progress -> review -> completed (with full task edit)
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", ['status' => 'review'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->put("/tasks/{$task->id}/status", [
                'status' => 'completed',
                'title' => 'Install Barricades Along Excavation Trench (Verified)',
                'description' => 'Updated scope after final inspection.',
                'priority' => 'critical',
            ])
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertEquals('Install Barricades Along Excavation Trench (Verified)', $task->title);
        $this->assertNotNull($task->completed_at);

        // Non-creator supervisor cannot delete task created by siteManager
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/tasks/{$task->id}")
            ->assertStatus(403);

        // Site manager can delete task and audit trail records task.deleted
        $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/tasks/{$task->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'task.deleted',
            'subject_id' => $task->id,
        ]);
    }

    public function test_field_notes_pin_unpin_convert_to_task_duplicate_prevention_and_deletion(): void
    {
        // 1. Mumbai supervisor logs a field note
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post('/tasks/notes', [
                'site_id' => $this->siteMumbai->id,
                'category' => 'safety_hse',
                'title' => 'Monsoon Dewatering Pump Check',
                'body' => 'Two 10HP diesel pumps tested and need fuel top-up.',
                'is_pinned' => 0,
            ])
            ->assertRedirect();

        $note = SiteNote::where('title', 'Monsoon Dewatering Pump Check')->firstOrFail();
        $this->assertFalse($note->is_pinned);

        // 2. Pin & Unpin authorization: Pune supervisor cannot pin Mumbai note (403)
        $this->actingAs($this->puneSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/tasks/notes/{$note->id}/toggle-pin")
            ->assertStatus(403);

        // Mumbai supervisor pins the note
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/tasks/notes/{$note->id}/toggle-pin")
            ->assertRedirect();
        $this->assertTrue($note->fresh()->is_pinned);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'site_note.pin_toggled',
            'subject_id' => $note->id,
        ]);

        // 3. Convert Field Note to Task
        $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/tasks/notes/{$note->id}/convert-to-task")
            ->assertRedirect();

        // Original note is preserved and linked to the generated task
        $this->assertDatabaseHas('site_notes', ['id' => $note->id]);
        $convertedInfo = $note->fresh()->convertedTaskInfo();
        $this->assertNotNull($convertedInfo);

        $generatedTask = Task::find($convertedInfo['task_id']);
        $this->assertNotNull($generatedTask);
        $this->assertEquals('Monsoon Dewatering Pump Check', $generatedTask->title);
        $this->assertEquals('critical', $generatedTask->priority); // safety_hse maps to critical

        // 4. Duplicate conversion prevention: repeating convert-to-task is rejected and does NOT create a second task
        $taskCountBefore = Task::count();
        $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->post("/tasks/notes/{$note->id}/convert-to-task")
            ->assertSessionHasErrors('note');
        $this->assertEquals($taskCountBefore, Task::count());

        // 5. Note deletion authorization: another supervisor who did not author the note cannot delete it
        $otherMumbaiSupervisor = $this->makeUser('Second Mumbai Sup', 'mumsup2@nsd.local', 'site_supervisor');
        $this->siteMumbai->assignedUsers()->attach([$otherMumbaiSupervisor->id]);

        $this->actingAs($otherMumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/tasks/notes/{$note->id}")
            ->assertStatus(403);

        // Note author (or site manager) can delete the note
        $this->actingAs($this->mumbaiSupervisor)
            ->withSession(['current_company_id' => $this->company->id])
            ->delete("/tasks/notes/{$note->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('site_notes', ['id' => $note->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'site_note.deleted',
            'subject_id' => $note->id,
        ]);
    }

    public function test_validation_failure_preserves_old_input_and_reopens_creation_drawer(): void
    {
        $followUp = $this->actingAs($this->siteManager)
            ->withSession(['current_company_id' => $this->company->id])
            ->followingRedirects()
            ->from('/tasks')
            ->post('/tasks', [
                'form_context' => 'create_task',
                'site_id' => $this->siteMumbai->id,
                'title' => '', // Invalid: required
                'description' => 'Preserved description after validation error',
                'priority' => 'high',
            ]);

        $followUp->assertStatus(200);
        $followUp->assertSee('showNewTask: true', false);
        $followUp->assertSee('Preserved description after validation error');
    }
}
