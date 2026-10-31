<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CrmNotification;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteNote;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Task::class);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        $accessibleSites = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])
            ? Site::where('status', 'active')->orderBy('name')->get()
            : $user->sites()->where('status', 'active')->orderBy('name')->get();

        $accessibleSiteIds = $accessibleSites->pluck('id')->all();

        $selectedSiteId = $request->has('site_id')
            ? $request->query('site_id')
            : session('active_site_id');

        if ($selectedSiteId && !$user->isAssignedToSite((int) $selectedSiteId) && !$user->hasRole(['office_staff'])) {
            abort(403, 'Unauthorized to view tasks for this site.');
        }

        $query = Task::with(['project', 'site', 'assignee', 'assignedWorker', 'creator'])
            ->when(!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']), function ($q) use ($accessibleSiteIds, $user) {
                $q->where(function ($sub) use ($accessibleSiteIds, $user) {
                    $sub->whereIn('site_id', $accessibleSiteIds)
                        ->orWhere('assigned_to', $user->id);
                });
            });

        if (!empty($selectedSiteId)) {
            $query->where('site_id', (int) $selectedSiteId);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->query('project_id'));
        }

        if ($request->filled('status') && array_key_exists($request->query('status'), Task::STATUSES)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority') && array_key_exists($request->query('priority'), Task::PRIORITIES)) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today()->format('Y-m-d'));
        }

        $tasks = $query->latest()->get();
        $viewMode = $request->query('view', 'board') === 'list' ? 'list' : 'board';

        $projects = Project::orderBy('name')->get();
        $companyUsers = User::whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $workers = Worker::where('status', 'active')
            ->when(!empty($selectedSiteId), fn ($q) => $q->where('assigned_site_id', (int) $selectedSiteId))
            ->orderBy('first_name')
            ->get();

        $siteNotes = Schema::hasTable('site_notes')
            ? SiteNote::with(['project', 'site', 'author'])
                ->when(!empty($selectedSiteId), fn ($q) => $q->where('site_id', (int) $selectedSiteId))
                ->when(empty($selectedSiteId) && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']), fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
                ->orderByDesc('is_pinned')
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        $recentActivity = Schema::hasTable('activity_logs')
            ? AuditLog::where('company_id', $companyId)
                ->when(!empty($selectedSiteId), fn ($q) => $q->where('site_id', (int) $selectedSiteId))
                ->with(['user', 'site'])
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        return view('tasks.index', compact(
            'tasks',
            'viewMode',
            'accessibleSites',
            'selectedSiteId',
            'projects',
            'companyUsers',
            'workers',
            'siteNotes',
            'recentActivity'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assigned_worker_id' => [
                'nullable',
                Rule::exists('workers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $site = Site::findOrFail((int) $validated['site_id']);
        if (!$user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to create tasks for an unassigned site.');
        }

        $nextNum = Task::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
        $code = sprintf('TSK-%s-%04d', now()->format('Y'), $nextNum);
        while (Task::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists()) {
            $nextNum++;
            $code = sprintf('TSK-%s-%04d', now()->format('Y'), $nextNum);
        }

        $task = Task::create([
            'company_id' => $companyId,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'code' => $code,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'] ?? 'todo',
            'due_date' => $validated['due_date'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'assigned_worker_id' => $validated['assigned_worker_id'] ?? null,
            'created_by' => $user->id,
        ]);

        AuditLog::record(
            'task.created',
            $task,
            null,
            [
                'code' => $task->code,
                'title' => $task->title,
                'site_id' => $task->site_id,
                'project_id' => $task->project_id,
                'priority' => $task->priority,
                'assigned_to' => $task->assigned_to,
            ],
            "Created task {$task->code}: {$task->title} at {$site->code}"
        );

        if ($task->assigned_to && (int) $task->assigned_to !== (int) $user->id) {
            CrmNotification::notifyUser(
                (int) $task->assigned_to,
                'task_assigned',
                "New Task Assigned: {$task->code}",
                "{$user->name} assigned you to '{$task->title}' at {$site->name}.",
                route('tasks.show', $task),
                $site->id,
                $companyId
            );
        }

        return redirect()->route('tasks.index', ['site_id' => $site->id])
            ->with('success', "Task {$task->code} created successfully.");
    }

    public function show(Task $task): View
    {
        Gate::authorize('view', $task);

        $task->load(['project', 'site', 'assignee', 'assignedWorker', 'creator', 'comments.user']);

        $companyUsers = User::whereHas('companies', fn ($q) => $q->where('companies.id', $task->company_id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $activityLogs = Schema::hasTable('activity_logs')
            ? AuditLog::where('company_id', $task->company_id)
                ->where('subject_type', Task::class)
                ->where('subject_id', $task->id)
                ->with('user')
                ->latest()
                ->get()
            : collect();

        return view('tasks.show', compact('task', 'companyUsers', 'activityLogs'));
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $user = Auth::user();
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $oldStatus = $task->status;
        $task->status = $validated['status'];
        if (isset($validated['priority'])) {
            $task->priority = $validated['priority'];
        }
        if (array_key_exists('due_date', $validated)) {
            $task->due_date = $validated['due_date'];
        }
        if (array_key_exists('assigned_to', $validated)) {
            $task->assigned_to = $validated['assigned_to'];
        }
        $task->completed_at = $validated['status'] === 'completed' ? Carbon::now() : null;
        $task->save();

        AuditLog::record(
            'task.status_updated',
            $task,
            ['status' => $oldStatus],
            ['status' => $task->status, 'site_id' => $task->site_id, 'project_id' => $task->project_id],
            "Task {$task->code} status updated from {$oldStatus} to {$task->status}"
        );

        // Notify creator if updated by assignee, or assignee if updated by manager
        $notifyUserId = ((int) $user->id === (int) $task->created_by) ? $task->assigned_to : $task->created_by;
        if ($notifyUserId && (int) $notifyUserId !== (int) $user->id) {
            CrmNotification::notifyUser(
                (int) $notifyUserId,
                'task_status',
                "Task {$task->code} Status: " . $task->statusLabel(),
                "{$user->name} updated '{$task->title}' to {$task->statusLabel()}.",
                route('tasks.show', $task),
                $task->site_id,
                $task->company_id
            );
        }

        return back()->with('success', "Task {$task->code} updated to {$task->statusLabel()}.");
    }

    public function storeComment(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $user = Auth::user();
        $validated = $request->validate([
            'comment' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $comment = TaskComment::create([
            'company_id' => $task->company_id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'comment' => $validated['comment'],
        ]);

        AuditLog::record(
            'task.comment_added',
            $task,
            null,
            ['comment_id' => $comment->id, 'site_id' => $task->site_id, 'project_id' => $task->project_id],
            "Comment posted on task {$task->code} by {$user->name}"
        );

        $notifyUserId = ((int) $user->id === (int) $task->assigned_to) ? $task->created_by : $task->assigned_to;
        if ($notifyUserId && (int) $notifyUserId !== (int) $user->id) {
            CrmNotification::notifyUser(
                (int) $notifyUserId,
                'task_comment',
                "New Comment on {$task->code}",
                "{$user->name} commented on '{$task->title}'.",
                route('tasks.show', $task),
                $task->site_id,
                $task->company_id
            );
        }

        return back()->with('success', 'Comment posted.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'category' => ['required', Rule::in(array_keys(SiteNote::CATEGORIES))],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:3000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $site = Site::findOrFail((int) $validated['site_id']);
        if (!$user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to post notes for an unassigned site.');
        }

        $note = SiteNote::create([
            'company_id' => $companyId,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'user_id' => $user->id,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned', false),
        ]);

        AuditLog::record(
            'site_note.created',
            $note,
            null,
            ['title' => $note->title, 'category' => $note->category, 'site_id' => $site->id, 'project_id' => $site->project_id],
            "Field note '{$note->title}' logged for {$site->code}"
        );

        return back()->with('success', 'Site field note recorded.');
    }

    public function notifications(): View
    {
        $user = Auth::user();
        $notifications = Schema::hasTable('crm_notifications')
            ? CrmNotification::where('user_id', $user->id)
                ->with('site')
                ->latest()
                ->paginate(25)
            : collect();

        return view('tasks.notifications', compact('notifications'));
    }

    public function markNotificationRead(CrmNotification $notification): RedirectResponse
    {
        if ((int) $notification->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $notification->update(['read_at' => Carbon::now()]);

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllNotificationsRead(): RedirectResponse
    {
        if (Schema::hasTable('crm_notifications')) {
            CrmNotification::where('user_id', Auth::id())
                ->whereNull('read_at')
                ->update(['read_at' => Carbon::now()]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}

