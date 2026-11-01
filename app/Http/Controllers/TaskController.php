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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            : Site::whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->orderBy('name')->get();

        $accessibleSiteIds = $accessibleSites->pluck('id')->all();

        $selectedSiteId = $request->has('site_id')
            ? $request->query('site_id')
            : session('active_site_id');

        if ($selectedSiteId && !$user->isAssignedToSite((int) $selectedSiteId) && !$user->hasRole(['office_staff'])) {
            abort(403, 'Unauthorized to view tasks for this site.');
        }

        // Base scope for KPIs and task listing (respects company + site + user accessibility)
        $baseScope = Task::query()
            ->when(!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']), function ($q) use ($accessibleSiteIds, $user) {
                $q->where(function ($sub) use ($accessibleSiteIds, $user) {
                    $sub->whereIn('site_id', $accessibleSiteIds)
                        ->orWhere('assigned_to', $user->id);
                });
            })
            ->when(!empty($selectedSiteId), fn ($q) => $q->where('site_id', (int) $selectedSiteId))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', (int) $request->query('project_id')));

        $todayStr = Carbon::today()->format('Y-m-d');

        $notesBaseScope = Schema::hasTable('site_notes')
            ? SiteNote::query()
                ->when(!empty($selectedSiteId), fn ($q) => $q->where('site_id', (int) $selectedSiteId))
                ->when(empty($selectedSiteId) && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']), fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
            : null;

        $kpis = [
            'total_active' => (clone $baseScope)->where('status', '!=', 'completed')->count(),
            'in_progress' => (clone $baseScope)->where('status', 'in_progress')->count(),
            'in_review' => (clone $baseScope)->where('status', 'review')->count(),
            'blocked' => (clone $baseScope)->where('status', 'blocked')->count(),
            'overdue' => (clone $baseScope)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $todayStr)
                ->count(),
            'my_tasks' => (clone $baseScope)
                ->where('assigned_to', $user->id)
                ->where('status', '!=', 'completed')
                ->count(),
            'pinned_notes' => $notesBaseScope ? (clone $notesBaseScope)->where('is_pinned', true)->count() : 0,
        ];

        $query = (clone $baseScope)->with(['project', 'site', 'assignee', 'assignedWorker', 'creator']);

        if ($request->filled('status') && array_key_exists($request->query('status'), Task::STATUSES)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority') && array_key_exists($request->query('priority'), Task::PRIORITIES)) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $todayStr);
        }

        if ($request->boolean('my_tasks')) {
            $query->where('assigned_to', $user->id);
        }

        $tasks = $query->latest()->get();

        $rawView = $request->query('view', 'board');
        $viewMode = in_array($rawView, ['board', 'list', 'notes'], true) ? $rawView : 'board';
        $noteCategory = $request->query('note_category');

        $projects = Project::orderBy('name')->get();
        $companyUsers = User::whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $workers = Worker::where('status', 'active')
            ->when(!empty($selectedSiteId), fn ($q) => $q->where('assigned_site_id', (int) $selectedSiteId))
            ->when(empty($selectedSiteId) && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']), fn ($q) => $q->whereIn('assigned_site_id', $accessibleSiteIds))
            ->orderBy('first_name')
            ->get();

        $siteNotes = $notesBaseScope
            ? (clone $notesBaseScope)
                ->with(['project', 'site', 'author'])
                ->when($noteCategory && array_key_exists($noteCategory, SiteNote::CATEGORIES), fn ($q) => $q->where('category', $noteCategory))
                ->orderByDesc('is_pinned')
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $recentActivity = Schema::hasTable('activity_logs')
            ? AuditLog::where('company_id', $companyId)
                ->when(!empty($selectedSiteId), fn ($q) => $q->where('site_id', (int) $selectedSiteId))
                ->when(empty($selectedSiteId) && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']), fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
                ->with(['user', 'site'])
                ->latest()
                ->limit(15)
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
            'recentActivity',
            'kpis',
            'noteCategory'
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

        $code = $this->generateNextTaskCode($companyId);

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
                'assigned_worker_id' => $task->assigned_worker_id,
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

        $user = Auth::user();
        $task->load(['project', 'site', 'assignee', 'assignedWorker', 'creator', 'comments.user']);

        $accessibleSites = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])
            ? Site::where('status', 'active')->orderBy('name')->get()
            : Site::whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->orderBy('name')->get();

        $companyUsers = User::whereHas('companies', fn ($q) => $q->where('companies.id', $task->company_id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $workers = Worker::where('status', 'active')
            ->where('company_id', $task->company_id)
            ->orderBy('first_name')
            ->get();

        $activityLogs = Schema::hasTable('activity_logs')
            ? AuditLog::where('company_id', $task->company_id)
                ->where('subject_type', Task::class)
                ->where('subject_id', $task->id)
                ->with('user')
                ->latest()
                ->get()
            : collect();

        return view('tasks.show', compact('task', 'accessibleSites', 'companyUsers', 'workers', 'activityLogs'));
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assigned_worker_id' => [
                'nullable',
                Rule::exists('workers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $oldValues = [
            'status' => $task->status,
            'title' => $task->title,
            'priority' => $task->priority,
            'site_id' => $task->site_id,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'assigned_to' => $task->assigned_to,
            'assigned_worker_id' => $task->assigned_worker_id,
        ];

        $newStatus = $validated['status'];
        if (!$task->canTransitionTo($newStatus)) {
            $fromLabel = $task->statusLabel();
            $toLabel = __(Task::STATUSES[$newStatus] ?? $newStatus);
            return back()
                ->withErrors(['status' => "Invalid status transition from {$fromLabel} to {$toLabel}."])
                ->withInput();
        }

        if (!empty($validated['site_id']) && (int) $validated['site_id'] !== (int) $task->site_id) {
            $newSite = Site::findOrFail((int) $validated['site_id']);
            if (!$user->isAssignedToSite($newSite)) {
                abort(403, 'Unauthorized to move task to an unassigned site.');
            }
            $task->site_id = $newSite->id;
            $task->project_id = $newSite->project_id;
        }

        if (!empty($validated['title'])) {
            $task->title = $validated['title'];
        }
        if ($request->has('description')) {
            $task->description = $validated['description'];
        }

        $oldStatus = $task->status;
        $oldAssignee = $task->assigned_to;

        $task->status = $newStatus;
        if (isset($validated['priority'])) {
            $task->priority = $validated['priority'];
        }
        if (array_key_exists('due_date', $validated)) {
            $task->due_date = $validated['due_date'];
        }
        if (array_key_exists('assigned_to', $validated)) {
            $task->assigned_to = $validated['assigned_to'];
        }
        if (array_key_exists('assigned_worker_id', $validated)) {
            $task->assigned_worker_id = $validated['assigned_worker_id'];
        }

        $task->completed_at = $newStatus === 'completed' ? Carbon::now() : null;
        $task->save();

        $action = $oldStatus !== $task->status ? 'task.status_updated' : 'task.updated';
        $desc = $oldStatus !== $task->status
            ? "Task {$task->code} status updated from {$oldStatus} to {$task->status}"
            : "Task {$task->code} details updated by {$user->name}";

        AuditLog::record(
            $action,
            $task,
            $oldValues,
            [
                'status' => $task->status,
                'title' => $task->title,
                'priority' => $task->priority,
                'site_id' => $task->site_id,
                'project_id' => $task->project_id,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'assigned_to' => $task->assigned_to,
                'assigned_worker_id' => $task->assigned_worker_id,
            ],
            $desc
        );

        if ($task->assigned_to && (int) $task->assigned_to !== (int) $oldAssignee && (int) $task->assigned_to !== (int) $user->id) {
            CrmNotification::notifyUser(
                (int) $task->assigned_to,
                'task_assigned',
                "Task Assigned: {$task->code}",
                "{$user->name} assigned you to '{$task->title}'.",
                route('tasks.show', $task),
                $task->site_id,
                $task->company_id
            );
        } elseif ($oldStatus !== $task->status) {
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
        }

        return back()->with('success', "Task {$task->code} updated ({$task->statusLabel()}).");
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $siteId = $task->site_id;
        $code = $task->code;
        $title = $task->title;

        AuditLog::record(
            'task.deleted',
            $task,
            [
                'code' => $code,
                'title' => $title,
                'status' => $task->status,
                'site_id' => $siteId,
                'project_id' => $task->project_id,
            ],
            null,
            "Deleted task {$code}: {$title}"
        );

        $task->delete();

        return redirect()->route('tasks.index', ['site_id' => $siteId])
            ->with('success', "Task {$code} deleted.");
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
        Gate::authorize('create', Task::class);

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

    public function toggleNotePin(SiteNote $note): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        if ((int) $note->company_id !== (int) $companyId) {
            abort(403, 'Cross-company note access denied.');
        }

        if (!$note->site_id || !$user->isAssignedToSite((int) $note->site_id)) {
            abort(403, 'Unauthorized to pin or unpin notes for an unassigned site.');
        }

        $oldPinned = (bool) $note->is_pinned;
        $note->is_pinned = !$oldPinned;
        $note->save();

        AuditLog::record(
            'site_note.pin_toggled',
            $note,
            ['is_pinned' => $oldPinned],
            ['is_pinned' => $note->is_pinned, 'site_id' => $note->site_id, 'project_id' => $note->project_id],
            ($note->is_pinned ? 'Pinned' : 'Unpinned') . " field note '{$note->title}'"
        );

        return back()->with('success', $note->is_pinned ? 'Field note pinned.' : 'Field note unpinned.');
    }

    public function destroyNote(SiteNote $note): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        if ((int) $note->company_id !== (int) $companyId) {
            abort(403, 'Cross-company note access denied.');
        }

        if (!$note->site_id || !$user->isAssignedToSite((int) $note->site_id)) {
            abort(403, 'Unauthorized to delete notes for an unassigned site.');
        }

        $canDelete = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || (int) $note->user_id === (int) $user->id;

        if (!$canDelete) {
            abort(403, 'Only site managers or the note author can delete this field note.');
        }

        $title = $note->title;
        $siteId = $note->site_id;
        $projectId = $note->project_id;
        $category = $note->category;

        AuditLog::record(
            'site_note.deleted',
            $note,
            ['title' => $title, 'category' => $category, 'site_id' => $siteId, 'project_id' => $projectId],
            null,
            "Deleted field note '{$title}'"
        );

        $note->delete();

        return back()->with('success', 'Field note deleted.');
    }

    public function convertNoteToTask(Request $request, SiteNote $note): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        if ((int) $note->company_id !== (int) $companyId) {
            abort(403, 'Cross-company note access denied.');
        }

        if (!$note->site_id || !$user->isAssignedToSite((int) $note->site_id)) {
            abort(403, 'Unauthorized to convert notes for an unassigned site.');
        }

        $lock = Cache::lock("convert_site_note_{$note->id}", 10);
        if (!$lock->get()) {
            return back()->withErrors(['note' => 'This field note is currently being converted.']);
        }

        try {
            return DB::transaction(function () use ($request, $note, $user, $companyId) {
                $lockedNote = SiteNote::withoutGlobalScopes()
                    ->where('id', $note->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingConversion = $lockedNote->convertedTaskInfo();
                if ($existingConversion !== null) {
                    return back()->withErrors([
                        'note' => "Field note '{$lockedNote->title}' was already converted to task {$existingConversion['task_code']}.",
                    ]);
                }

                $defaultPriority = match ($lockedNote->category) {
                    'safety_hse' => 'critical',
                    'client_instruction', 'quality' => 'high',
                    default => 'medium',
                };

                $priority = $request->input('priority', $defaultPriority);
                if (!array_key_exists($priority, Task::PRIORITIES)) {
                    $priority = $defaultPriority;
                }

                $code = $this->generateNextTaskCode($companyId);

                $task = Task::create([
                    'company_id' => $companyId,
                    'project_id' => $lockedNote->project_id,
                    'site_id' => $lockedNote->site_id,
                    'code' => $code,
                    'title' => $lockedNote->title,
                    'description' => "[Converted from Field Note #{$lockedNote->id} — {$lockedNote->categoryLabel()}]\n\n" . $lockedNote->body,
                    'priority' => $priority,
                    'status' => 'todo',
                    'due_date' => now()->addDays(2)->format('Y-m-d'),
                    'created_by' => $user->id,
                ]);

                AuditLog::record(
                    'site_note.converted_to_task',
                    $lockedNote,
                    null,
                    [
                        'task_id' => $task->id,
                        'task_code' => $task->code,
                        'site_id' => $lockedNote->site_id,
                        'project_id' => $lockedNote->project_id,
                    ],
                    "Converted field note '{$lockedNote->title}' to task {$task->code}"
                );

                AuditLog::record(
                    'task.created',
                    $task,
                    null,
                    [
                        'code' => $task->code,
                        'title' => $task->title,
                        'site_id' => $task->site_id,
                        'project_id' => $task->project_id,
                        'source_note_id' => $lockedNote->id,
                    ],
                    "Created task {$task->code} from field note #{$lockedNote->id}"
                );

                return back()->with('success', "Field note converted to Task {$task->code}.");
            });
        } finally {
            $lock->release();
        }
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

    private function generateNextTaskCode(int $companyId): string
    {
        $nextNum = Task::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
        $code = sprintf('TSK-%s-%04d', now()->format('Y'), $nextNum);
        while (Task::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists()) {
            $nextNum++;
            $code = sprintf('TSK-%s-%04d', now()->format('Y'), $nextNum);
        }

        return $code;
    }
}
