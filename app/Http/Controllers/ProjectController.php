<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectMilestone;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\User;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        // Scope projects for site managers / supervisors to only those linked to their assigned sites
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
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
        if (!empty($activeSiteId)) {
            $query->where(function ($q) use ($activeSiteId) {
                $q->whereHas('sites', fn ($sq) => $sq->where('sites.id', $activeSiteId));
                if (Schema::hasTable('project_sites')) {
                    $q->orWhereHas('linkedSites', fn ($lsq) => $lsq->where('sites.id', $activeSiteId));
                }
            });
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
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

        $managers = $this->getCompanyStaff();
        $sites = Site::orderBy('code')->get();
        $statuses = Project::STATUSES;

        return view('projects.create', compact('managers', 'sites', 'statuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $companyId = TenantContext::getCompanyId();
        $validated = $this->validateProject($request, $companyId);

        $project = DB::transaction(function () use ($validated, $companyId, $request) {
            $project = Project::create([
                'company_id' => $companyId,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'client_name' => $validated['client_name'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'status' => $validated['status'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncProjectSites($project, (array) $request->input('site_ids', []));

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
        if (!in_array($activeTab, ['overview', 'sites', 'milestones', 'documents', 'activity'], true)) {
            $activeTab = 'overview';
        }

        $project->load(['manager']);

        $allProjectSites = $project->allSites();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
            $accessibleIds = $user->accessibleSiteIds();
            $visibleSites = $allProjectSites->filter(fn (Site $s) => in_array($s->id, $accessibleIds, true))->values();
        } else {
            $visibleSites = $allProjectSites;
        }

        $visibleSiteIds = $visibleSites->pluck('id')->all();

        $milestones = collect();
        if (Schema::hasTable('project_milestones')) {
            $milestoneQuery = $project->milestones()->with(['site', 'creator']);
            if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
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
        if (Schema::hasTable('site_assignments') && !empty($visibleSiteIds)) {
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

        $managers = $this->getCompanyStaff();
        $sites = Site::orderBy('code')->get();
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

        if (!empty($validated['site_id']) && !Auth::user()->isAssignedToSite((int) $validated['site_id'])) {
            abort(403, 'Unauthorized to create a milestone for an unassigned site.');
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

        if ($milestone->site_id && !Auth::user()->isAssignedToSite((int) $milestone->site_id)) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ProjectMilestone::STATUSES))],
            'percent_complete' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $percent = (int) $validated['percent_complete'];
        $status = $validated['status'];
        if ($percent === 100) {
            $status = 'completed';
        } elseif ($status === 'completed') {
            $percent = 100;
        }

        $milestone->update([
            'status' => $status,
            'percent_complete' => $percent,
            'completed_at' => $status === 'completed' ? ($milestone->completed_at ?? Carbon::now()) : null,
        ]);

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'milestones'])
            ->with('success', 'Milestone progress updated.');
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

        if (!empty($validated['site_id']) && !Auth::user()->isAssignedToSite((int) $validated['site_id'])) {
            abort(403, 'Unauthorized to upload a document for an unassigned site.');
        }

        /** @var UploadedFile $file */
        $file = $request->file('document');
        $originalName = basename($file->getClientOriginalName());
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ProjectDocument::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['document' => ['Unsupported file extension.']]);
        }

        $detectedMime = $file->getMimeType();
        if (!is_string($detectedMime) || !in_array($detectedMime, ProjectDocument::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['document' => ['Unsupported file content MIME type.']]);
        }

        $docRecord = DB::transaction(function () use ($project, $validated, $file, $originalName, $ext, $detectedMime, $companyId) {
            $existingMaxVersion = ProjectDocument::where('project_id', $project->id)
                ->where('document_type', $validated['document_type'])
                ->where('title', trim($validated['title']))
                ->when(!empty($validated['site_id']), fn ($q) => $q->where('site_id', $validated['site_id']), fn ($q) => $q->whereNull('site_id'))
                ->max('version');

            $nextVersion = ((int) $existingMaxVersion) + 1;
            $generatedName = Str::uuid()->toString() . '.' . $ext;
            $directory = "project-documents/{$companyId}/{$project->id}";
            $storedPath = $file->storeAs($directory, $generatedName, 'local');

            return ProjectDocument::create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'site_id' => $validated['site_id'] ?? null,
                'document_type' => $validated['document_type'],
                'title' => trim($validated['title']),
                'version' => $nextVersion,
                'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'project-document', 0, 255),
                'storage_disk' => 'local',
                'file_path' => $storedPath,
                'mime_type' => $detectedMime,
                'file_size' => (int) $file->getSize(),
                'visibility_roles' => !empty($validated['visibility_roles']) ? array_values($validated['visibility_roles']) : null,
                'uploaded_by' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'documents'])
            ->with('success', "Document '{$docRecord->title}' (v{$docRecord->version}) uploaded to private storage.");
    }

    public function downloadDocument(Project $project, int $document): StreamedResponse
    {
        $doc = ProjectDocument::where('project_id', $project->id)->findOrFail($document);

        Gate::authorize('downloadDocument', [$project, $doc]);

        if ($doc->storage_disk !== 'local' || str_contains($doc->file_path, '..')) {
            abort(403, 'Invalid project document storage path.');
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($doc->file_path)) {
            abort(404, 'Project document file not found in private storage.');
        }

        $safeDownloadName = sprintf('v%d_%s', $doc->version, preg_replace('/[^A-Za-z0-9._\- ]/', '_', basename($doc->original_name)) ?: 'document');

        return $disk->download($doc->file_path, $safeDownloadName, [
            'Content-Type' => $doc->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyDocument(Project $project, int $document): RedirectResponse
    {
        $doc = ProjectDocument::where('project_id', $project->id)->findOrFail($document);

        Gate::authorize('deleteDocument', [$project, $doc]);

        $disk = Storage::disk('local');
        if ($doc->storage_disk === 'local' && !str_contains($doc->file_path, '..') && $disk->exists($doc->file_path)) {
            $disk->delete($doc->file_path);
        }

        $doc->delete();

        return redirect()
            ->route('projects.show', ['project' => $project->id, 'tab' => 'documents'])
            ->with('success', 'Project document removed.');
    }

    protected function syncProjectSites(Project $project, array $siteIds): void
    {
        if (!Schema::hasTable('project_sites')) {
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
        $codeRule = Rule::unique('projects', 'code')->where(fn ($q) => $q->where('company_id', $companyId));
        if ($ignoreProjectId !== null) {
            $codeRule->ignore($ignoreProjectId);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:30', $codeRule],
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
        ]);
    }

    protected function getCompanyStaff()
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId) {
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

