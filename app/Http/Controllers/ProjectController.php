<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectMilestone;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\User;
use App\Services\Security\TenantContext;
use App\Services\Storage\VaultStorage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $user = Auth::user();
        $query = Project::with(['manager', 'sites']);

        if (Schema::hasTable('project_milestones')) {
            $query->withCount(['milestones', 'documents']);
        }

        // Scope projects for site-restricted users to only those linked to their assigned sites
        $hasUnrestrictedSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || (! $user->isRestrictedToAssignedSites() && $user->hasRole(['senior_manager', 'office_staff']));

        if (! $hasUnrestrictedSiteAccess) {
            $accessibleSiteIds = $user->accessibleSiteIds();
            $query->where(function ($q) use ($user, $accessibleSiteIds) {
                $q->where('projects.manager_id', $user->id)
                    ->orWhereHas('sites', fn ($sq) => $sq->whereIn('sites.id', $accessibleSiteIds));

                if (Schema::hasTable('project_sites')) {
                    $q->orWhereHas('linkedSites', fn ($lsq) => $lsq->whereIn('sites.id', $accessibleSiteIds));
                }
            });
        }

        // Apply active topbar site switcher filter if present
        $activeSiteId = $request->query('site_id', session('active_site_id'));
        if (! empty($activeSiteId)) {
            $query->where(function ($q) use ($activeSiteId) {
                $q->whereHas('sites', fn ($sq) => $sq->where('sites.id', $activeSiteId));
                if (Schema::hasTable('project_sites')) {
                    $q->orWhereHas('linkedSites', fn ($lsq) => $lsq->where('sites.id', $activeSiteId));
                }
            });
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('client_name', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $projects = $query->orderBy('code')->paginate(12)->withQueryString();
        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', '');

        return view('projects.index', compact('projects', 'search', 'status'));
    }

    public function create(): View
    {
        Gate::authorize('create', Project::class);

        $user = Auth::user();
        $managers = $this->getCompanyStaff();
        $sitesQuery = Site::orderBy('code');
        if ($user && $user->isRestrictedToAssignedSites()) {
            $sitesQuery->whereIn('id', $user->accessibleSiteIds());
        }
        $sites = $sitesQuery->get();
        $statuses = Project::STATUSES;
        $nextNum = Project::count() + 1;
        $suggestedCode = sprintf('PRJ-%03d', $nextNum);
        while (Project::where('code', $suggestedCode)->exists()) {
            $nextNum++;
            $suggestedCode = sprintf('PRJ-%03d', $nextNum);
        }

        return view('projects.create', compact('managers', 'sites', 'statuses', 'suggestedCode'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $companyId = TenantContext::getCompanyId();
        $validated = $this->validateProject($request, $companyId);

        $project = DB::transaction(function () use ($validated, $companyId, $request) {
            $code = trim((string) ($validated['code'] ?? ''));

            if ($code === '' || preg_match('/^PRJ-\d+$/i', $code)) {
                if (Schema::hasTable('worker_sequences') && $companyId) {
                    $seq = DB::table('worker_sequences')
                        ->where('company_id', $companyId)
                        ->where('prefix', 'PRJ')
                        ->where('year', 0)
                        ->lockForUpdate()
                        ->first();

                    if (! $seq) {
                        DB::table('worker_sequences')->insertOrIgnore([
                            'company_id' => $companyId,
                            'prefix' => 'PRJ',
                            'year' => 0,
                            'last_number' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if ($code === '' || Project::where('company_id', $companyId)->where('code', $code)->lockForUpdate()->exists()) {
                    $nextNum = Project::where('company_id', $companyId)->count() + 1;
                    $code = sprintf('PRJ-%03d', $nextNum);
                    while (Project::where('company_id', $companyId)->where('code', $code)->exists()) {
                        $nextNum++;
                        $code = sprintf('PRJ-%03d', $nextNum);
                    }
                }
            }

            $project = Project::create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $validated['name'],
                'client_name' => $validated['client_name'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'status' => $validated['status'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncProjectSites($project, (array) $request->input('site_ids', []));

            AuditLog::record(
                'project.created',
                $project,
                null,
                ['code' => $project->code, 'name' => $project->name, 'status' => $project->status],
                "Created project {$project->name} ({$project->code})"
            );

            return $project;
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Project {$project->name} ({$project->code}) created successfully.");
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        $user = Auth::user();
        $activeTab = $request->query('tab', 'overview');
        if (! in_array($activeTab, ['overview', 'sites', 'milestones', 'documents', 'activity'], true)) {
            $activeTab = 'overview';
        }

        $project->load(['manager']);

        $allProjectSites = $project->allSites();
        $hasUnrestrictedSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || (! $user->isRestrictedToAssignedSites() && $user->hasRole(['senior_manager', 'office_staff']));

        if (! $hasUnrestrictedSiteAccess) {
            $accessibleIds = $user->accessibleSiteIds();
            $visibleSites = $allProjectSites->filter(fn (Site $s) => in_array($s->id, $accessibleIds, true))->values();
        } else {
            $visibleSites = $allProjectSites;
        }

        $visibleSiteIds = $visibleSites->pluck('id')->all();

        $milestones = collect();
        if (Schema::hasTable('project_milestones')) {
            $milestoneQuery = $project->milestones()->with(['site', 'creator']);
            if (! $hasUnrestrictedSiteAccess) {
                $milestoneQuery->where(function ($q) use ($visibleSiteIds) {
                    $q->whereNull('site_id')->orWhereIn('site_id', $visibleSiteIds);
                });
            }
            $milestones = $milestoneQuery->get();
        }

        $documents = collect();
        if (Schema::hasTable('project_documents')) {
            $allDocs = $project->documents()->with(['site', 'uploader'])->get();
            $documents = $allDocs->filter(fn (ProjectDocument $doc) => $doc->isVisibleTo($user))->values();
        }

        $recentAssignments = collect();
        if (Schema::hasTable('site_assignments') && ! empty($visibleSiteIds)) {
            $recentAssignments = SiteAssignment::whereIn('site_id', $visibleSiteIds)
                ->with(['site', 'user', 'worker', 'assigner'])
                ->latest()
                ->limit(20)
                ->get();
        }

        $documentTypes = ProjectDocument::DOCUMENT_TYPES;
        $milestoneStatuses = ProjectMilestone::STATUSES;
        $overallProgress = $project->overallProgressPercent();
        $allSites = $visibleSites;
        $visibleDocuments = $documents;
        $recentLogs = collect();
        if (Schema::hasTable('activity_logs')) {
            $recentLogs = AuditLog::forCompany((int) $project->company_id)
                ->with(['user', 'site'])
                ->where(function ($q) use ($project, $visibleSiteIds) {
                    $q->where('project_id', $project->id)
                        ->orWhere(function ($sub) use ($project) {
                            $sub->where('subject_type', Project::class)
                                ->where('subject_id', $project->id);
                        });
                    if (! empty($visibleSiteIds)) {
                        $q->orWhereIn('site_id', $visibleSiteIds);
                    }
                })
                ->when(! $hasUnrestrictedSiteAccess, function ($q) use ($visibleSiteIds) {
                    $q->where(function ($sub) use ($visibleSiteIds) {
                        $sub->whereNull('site_id')
                            ->orWhereIn('site_id', $visibleSiteIds);
                    });
                })
                ->where('action', 'not like', 'auth.%')
                ->where('action', 'not like', 'payroll.%')
                ->latest('created_at')
                ->latest('id')
                ->limit(30)
                ->get();
        }

        return view('projects.show', compact(
            'project',
            'activeTab',
            'visibleSites',
            'allSites',
            'milestones',
            'documents',
            'visibleDocuments',
            'recentAssignments',
            'recentLogs',
            'overallProgress',
            'documentTypes',
            'milestoneStatuses'
        ));
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        $user = Auth::user();
        $managers = $this->getCompanyStaff();
        $sitesQuery = Site::orderBy('code');
        if ($user && $user->isRestrictedToAssignedSites()) {
            $sitesQuery->whereIn('id', $user->accessibleSiteIds());
        }
        $sites = $sitesQuery->get();
        $statuses = Project::STATUSES;
        $linkedSiteIds = $project->allSites()->pluck('id')->all();

        return view('projects.edit', compact('project', 'managers', 'sites', 'statuses', 'linkedSiteIds'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $companyId = TenantContext::getCompanyId();
        $validated = $this->validateProject($request, $companyId, $project->id);

        DB::transaction(function () use ($project, $validated, $request) {
            $oldValues = ['code' => $project->code, 'name' => $project->name, 'status' => $project->status];

            $project->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'client_name' => $validated['client_name'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'status' => $validated['status'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            if ($request->has('site_ids')) {
                $this->syncProjectSites($project, (array) $request->input('site_ids', []));
            }

            AuditLog::record(
                'project.updated',
                $project,
                $oldValues,
                ['code' => $project->code, 'name' => $project->name, 'status' => $project->status],
                "Updated project {$project->name} ({$project->code})"
            );
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Project {$project->name} ({$project->code}) updated successfully.");
    }

    public function storeMilestone(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageMilestones', $project);

        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(ProjectMilestone::STATUSES))],
            'percent_complete' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        if (! empty($validated['site_id'])) {
            $siteId = (int) $validated['site_id'];
            if (! Auth::user()->isAssignedToSite($siteId)) {
                abort(403, 'Unauthorized to create a milestone for an unassigned site.');
            }
            $projectSiteIds = $project->allSites()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! in_array($siteId, $projectSiteIds, true)) {
                throw ValidationException::withMessages([
                    'site_id' => ['The selected site does not belong to this project.'],
                ]);
            }
        }

        $percent = (int) $validated['percent_complete'];
        $status = $validated['status'];
        if ($percent === 100) {
            $status = 'completed';
        } elseif ($status === 'completed') {
            $percent = 100;
        }

        ProjectMilestone::create([
            'company_id' => $companyId,
            'project_id' => $project->id,
            'site_id' => $validated['site_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => $status,
            'percent_complete' => $percent,
            'completed_at' => $status === 'completed' ? Carbon::now() : null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'milestones'])
            ->with('success', 'Project milestone added.');
    }

    public function updateMilestone(Request $request, Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        Gate::authorize('manageMilestones', $project);

        if ((int) $milestone->project_id !== (int) $project->id) {
            abort(404);
        }

        if ($milestone->site_id && ! Auth::user()->isAssignedToSite((int) $milestone->site_id)) {
            abort(403);
        }

        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'status' => ['required', Rule::in(array_keys(ProjectMilestone::STATUSES))],
            'percent_complete' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        if (array_key_exists('site_id', $validated) && ! empty($validated['site_id'])) {
            $siteId = (int) $validated['site_id'];
            if (! Auth::user()->isAssignedToSite($siteId)) {
                abort(403, 'Unauthorized to assign milestone to an unassigned site.');
            }
            $projectSiteIds = $project->allSites()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! in_array($siteId, $projectSiteIds, true)) {
                throw ValidationException::withMessages([
                    'site_id' => ['The selected site does not belong to this project.'],
                ]);
            }
        }

        $percent = (int) $validated['percent_complete'];
        $status = $validated['status'];
        if ($percent === 100) {
            $status = 'completed';
        } elseif ($status === 'completed') {
            $percent = 100;
        }

        $updates = [
            'status' => $status,
            'percent_complete' => $percent,
            'completed_at' => $status === 'completed' ? ($milestone->completed_at ?? Carbon::now()) : null,
        ];
        if (! empty($validated['title'])) {
            $updates['title'] = trim($validated['title']);
        }
        if (array_key_exists('description', $validated)) {
            $updates['description'] = $validated['description'];
        }
        if (array_key_exists('due_date', $validated)) {
            $updates['due_date'] = $validated['due_date'];
        }
        if (array_key_exists('site_id', $validated)) {
            $updates['site_id'] = $validated['site_id'] ?? null;
        }

        $milestone->update($updates);

        AuditLog::record(
            'project.milestone.updated',
            $milestone,
            null,
            ['project_id' => $project->id, 'title' => $milestone->title, 'status' => $status, 'percent_complete' => $percent],
            "Updated milestone '{$milestone->title}' on project {$project->code}"
        );

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'milestones'])
            ->with('success', 'Milestone updated.');
    }

    public function destroyMilestone(Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        Gate::authorize('manageMilestones', $project);

        if ((int) $milestone->project_id !== (int) $project->id) {
            abort(404);
        }

        if ($milestone->site_id && ! Auth::user()->isAssignedToSite((int) $milestone->site_id)) {
            abort(403);
        }

        $title = $milestone->title;
        $milestoneId = $milestone->id;
        $milestone->delete();

        AuditLog::record(
            'project.milestone.deleted',
            $project,
            ['milestone_id' => $milestoneId, 'title' => $title],
            null,
            "Deleted milestone '{$title}' from project {$project->code}"
        );

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'milestones'])
            ->with('success', "Milestone '{$title}' deleted.");
    }

    public function storeDocument(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('uploadDocument', $project);

        $companyId = TenantContext::getCompanyId();
        $allowedTypes = implode(',', array_keys(ProjectDocument::DOCUMENT_TYPES));
        $allowedExts = implode(',', ProjectDocument::ALLOWED_EXTENSIONS);
        $allowedMimes = implode(',', ProjectDocument::ALLOWED_MIME_TYPES);
        $maxKb = ProjectDocument::MAX_FILE_SIZE_KB;

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'document_type' => ['required', 'string', "in:{$allowedTypes}"],
            'title' => ['required', 'string', 'max:200'],
            'document' => [
                'required',
                'file',
                'min:1',
                "max:{$maxKb}",
                "extensions:{$allowedExts}",
                "mimetypes:{$allowedMimes}",
            ],
            'visibility_roles' => ['nullable', 'array'],
            'visibility_roles.*' => ['string', 'max:50'],
        ]);

        if (! empty($validated['site_id'])) {
            $siteId = (int) $validated['site_id'];
            if (! Auth::user()->isAssignedToSite($siteId)) {
                abort(403, 'Unauthorized to upload a document for an unassigned site.');
            }
            $projectSiteIds = $project->allSites()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! in_array($siteId, $projectSiteIds, true)) {
                throw ValidationException::withMessages([
                    'site_id' => ['The selected site does not belong to this project.'],
                ]);
            }
        }

        /** @var UploadedFile $file */
        $file = $request->file('document');
        $originalName = basename($file->getClientOriginalName());
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ProjectDocument::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['document' => ['Unsupported file extension.']]);
        }

        $detectedMime = $file->getMimeType();
        if (! is_string($detectedMime) || ! in_array($detectedMime, ProjectDocument::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['document' => ['Unsupported file content MIME type.']]);
        }

        $docRecord = DB::transaction(function () use ($project, $validated, $file, $originalName, $ext, $detectedMime, $companyId) {
            $existingMaxVersion = ProjectDocument::where('project_id', $project->id)
                ->where('document_type', $validated['document_type'])
                ->where('title', trim($validated['title']))
                ->when(! empty($validated['site_id']), fn ($q) => $q->where('site_id', $validated['site_id']), fn ($q) => $q->whereNull('site_id'))
                ->max('version');

            $nextVersion = ((int) $existingMaxVersion) + 1;
            $generatedName = Str::uuid()->toString().'.'.$ext;
            $directory = "project-documents/{$companyId}/{$project->id}";
            $stored = VaultStorage::storeUploaded($file, $directory, $generatedName);
            $storedPath = $stored['path'];
            $diskName = $stored['disk'];

            try {
                return ProjectDocument::create([
                    'company_id' => $companyId,
                    'project_id' => $project->id,
                    'site_id' => $validated['site_id'] ?? null,
                    'document_type' => $validated['document_type'],
                    'title' => trim($validated['title']),
                    'version' => $nextVersion,
                    'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'project-document', 0, 255),
                    'storage_disk' => $diskName,
                    'file_path' => $storedPath,
                    'mime_type' => $detectedMime,
                    'file_size' => (int) $file->getSize(),
                    'visibility_roles' => ! empty($validated['visibility_roles']) ? array_values($validated['visibility_roles']) : null,
                    'uploaded_by' => Auth::id(),
                ]);
            } catch (\Throwable $e) {
                VaultStorage::delete($storedPath, $diskName);
                throw $e;
            }
        });

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'documents'])
            ->with('success', "Document '{$docRecord->title}' (v{$docRecord->version}) uploaded to private storage.");
    }

    public function downloadDocument(Project $project, int $document): StreamedResponse
    {
        $doc = ProjectDocument::where('project_id', $project->id)->findOrFail($document);

        Gate::authorize('downloadDocument', [$project, $doc]);

        if (! in_array($doc->storage_disk, ['local', 'vault'], true) || str_contains($doc->file_path, '..')) {
            abort(403, 'Invalid project document storage path.');
        }

        if (! VaultStorage::exists($doc->file_path, $doc->storage_disk)) {
            abort(404, 'Project document file not found in private storage.');
        }

        $safeDownloadName = sprintf('v%d_%s', $doc->version, preg_replace('/[^A-Za-z0-9._\- ]/', '_', basename($doc->original_name)) ?: 'document');

        return VaultStorage::download($doc->file_path, $safeDownloadName, [
            'Content-Type' => $doc->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], $doc->storage_disk);
    }

    public function destroyDocument(Project $project, int $document): RedirectResponse
    {
        $doc = ProjectDocument::where('project_id', $project->id)->findOrFail($document);

        Gate::authorize('deleteDocument', [$project, $doc]);

        if (in_array($doc->storage_disk, ['local', 'vault'], true) && ! str_contains($doc->file_path, '..')) {
            VaultStorage::delete($doc->file_path, $doc->storage_disk);
        }

        $doc->delete();

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'documents'])
            ->with('success', 'Project document removed.');
    }

    protected function syncProjectSites(Project $project, array $siteIds): void
    {
        if (! Schema::hasTable('project_sites')) {
            return;
        }

        $validSites = Site::where('company_id', $project->company_id)
            ->whereIn('id', $siteIds)
            ->get();

        $syncPayload = [];
        foreach ($validSites as $site) {
            $syncPayload[$site->id] = [
                'company_id' => $project->company_id,
                'start_date' => $project->start_date?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d'),
                'is_primary' => (int) $site->project_id === (int) $project->id,
            ];
        }

        $project->linkedSites()->sync($syncPayload);
    }

    protected function validateProject(Request $request, ?int $companyId, ?int $ignoreProjectId = null): array
    {
        $rawCode = strtoupper(trim((string) $request->input('code', '')));
        if ($rawCode !== '') {
            $request->merge(['code' => $rawCode]);
        }

        $isAutoCodeOnCreate = $ignoreProjectId === null && ($rawCode === '' || (bool) preg_match('/^PRJ-\d+$/', $rawCode));

        $codeRules = [$ignoreProjectId === null ? 'nullable' : 'required', 'string', 'max:30'];
        if (! $isAutoCodeOnCreate) {
            $codeRule = Rule::unique('projects', 'code')->where(fn ($q) => $q->where('company_id', $companyId));
            if ($ignoreProjectId !== null) {
                $codeRule->ignore($ignoreProjectId);
            }
            $codeRules[] = $codeRule;
        }

        return $request->validate([
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)->where('status', 'active')),
            ],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ], [
            'code.unique' => 'This Project Code is already in use by another project in your company. Please enter a unique Project Code.',
            'end_date.after_or_equal' => 'Project completion date cannot be earlier than the start date.',
        ]);
    }

    protected function getCompanyStaff()
    {
        $companyId = TenantContext::getCompanyId();
        if (! $companyId) {
            return collect();
        }

        return User::where('is_active', true)
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('companies.id', $companyId)
                    ->where('company_user.status', 'active');
            })
            ->orderBy('name')
            ->get();
    }
}
