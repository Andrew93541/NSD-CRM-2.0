<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Site::class);

        $user = Auth::user();
        $hasExtendedColumns = Schema::hasColumn('sites', 'address');

        $query = Site::with(['project', 'supervisor'])
            ->withCount(['workers', 'workOrders', 'warehouses', 'purchaseRequests']);

        // Enforce server-side site-level visibility for non-executive roles
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            $accessibleIds = $user->accessibleSiteIds();
            $query->whereIn('sites.id', $accessibleIds);
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($q) use ($search, $hasExtendedColumns) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('location', 'like', $search);

                if ($hasExtendedColumns) {
                    $q->orWhere('address', 'like', $search)
                        ->orWhere('city', 'like', $search)
                        ->orWhere('state', 'like', $search)
                        ->orWhere('postal_code', 'like', $search);
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        $sites = $query->orderBy('code')->paginate(12)->withQueryString();
        $projects = Project::orderBy('name')->get();
        $hasFilters = $request->filled('search') || $request->filled('status') || $request->filled('project_id');

        return view('sites.index', compact('sites', 'projects', 'hasFilters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Site::class);

        $projects = Project::orderBy('name')->get();
        $supervisors = $this->getCompanySupervisors();

        return view('sites.create', compact('projects', 'supervisors'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Site::class);

        $companyId = TenantContext::getCompanyId();
        $validated = $this->validateSite($request, $companyId);

        $payload = $this->filterPersistableColumns($validated);
        $payload['company_id'] = $companyId;

        $site = Site::create($payload);

        $this->syncSiteRelations($site);

        return redirect()
            ->route('sites.show', $site)
            ->with('success', "Site {$site->name} ({$site->code}) created successfully.");
    }

    public function show(Site $site): View
    {
        Gate::authorize('view', $site);

        $site->load(['project.manager', 'supervisor', 'assignedUsers', 'warehouses.manager']);
        $site->loadCount(['workers', 'workOrders', 'attendances', 'purchaseRequests', 'warehouses']);

        $today = Carbon::today()->format('Y-m-d');

        $assignedWorkers = Worker::where('assigned_site_id', $site->id)
            ->with('subcontractor')
            ->orderBy('first_name')
            ->take(10)
            ->get();

        $todayAttendances = DailyAttendance::where('site_id', $site->id)
            ->whereDate('attendance_date', $today)
            ->with('worker')
            ->get();

        $uniqueWorkersPresentToday = $todayAttendances
            ->whereIn('status', ['present', 'half_day'])
            ->pluck('worker_id')
            ->unique()
            ->count();

        $hoursToday = round((float) $todayAttendances->sum('worked_hours'), 1);

        $recentWorkOrders = WorkOrder::where('site_id', $site->id)
            ->with(['creator', 'siteManager', 'seniorManager'])
            ->latest()
            ->take(8)
            ->get();

        $recentPurchaseRequests = PurchaseRequest::where('site_id', $site->id)
            ->with(['requester', 'warehouse', 'items.material'])
            ->latest()
            ->take(6)
            ->get();

        $siteAssignments = Schema::hasTable('site_assignments')
            ? $site->siteAssignments()->with(['user', 'worker', 'assigner'])->limit(25)->get()
            : collect();

        $companyUsers = $this->getCompanySupervisors();
        $companyWorkers = Worker::orderBy('first_name')->get();

        return view('sites.show', compact(
            'site',
            'assignedWorkers',
            'today',
            'uniqueWorkersPresentToday',
            'hoursToday',
            'recentWorkOrders',
            'recentPurchaseRequests',
            'siteAssignments',
            'companyUsers',
            'companyWorkers'
        ));
    }

    public function edit(Site $site): View
    {
        Gate::authorize('update', $site);

        $projects = Project::orderBy('name')->get();
        $supervisors = $this->getCompanySupervisors();

        return view('sites.edit', compact('site', 'projects', 'supervisors'));
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site);

        $companyId = TenantContext::getCompanyId();
        $validated = $this->validateSite($request, $companyId, $site->id);

        $payload = $this->filterPersistableColumns($validated);
        $site->fill($payload);
        $site->save();

        $this->syncSiteRelations($site);

        return redirect()
            ->route('sites.show', $site)
            ->with('success', "Site {$site->name} ({$site->code}) updated successfully.");
    }

    public function storeAssignment(Request $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site);

        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                'required_without:worker_id',
                Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)->where('status', 'active')),
            ],
            'worker_id' => [
                'nullable',
                'integer',
                'required_without:user_id',
                Rule::exists('workers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'role_on_site' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'transfer_exclusive' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['user_id'])) {
            $staffUser = User::findOrFail($validated['user_id']);
            SiteAssignment::recordUserAssignment(
                $site,
                $staffUser,
                $validated['role_on_site'],
                $validated['start_date'],
                Auth::id(),
                $validated['notes'] ?? null,
                (bool) ($validated['transfer_exclusive'] ?? false)
            );
        }

        if (!empty($validated['worker_id'])) {
            $worker = Worker::findOrFail($validated['worker_id']);
            $worker->assigned_site_id = $site->id;
            $worker->save();

            SiteAssignment::recordWorkerAssignment(
                $worker,
                $site->id,
                $validated['start_date'],
                Auth::id(),
                $validated['notes'] ?? 'Transferred to ' . $site->code
            );
        }

        return back()->with('success', 'Dated site assignment recorded successfully.');
    }

    protected function syncSiteRelations(Site $site): void
    {
        if (!empty($site->supervisor_id)) {
            $supervisor = User::find($site->supervisor_id);
            if ($supervisor) {
                SiteAssignment::recordUserAssignment($site, $supervisor, 'supervisor', null, Auth::id(), 'Site supervisor assignment');
            }
        }

        if (Schema::hasColumn('sites', 'site_manager_id') && !empty($site->attributes['site_manager_id'])) {
            $manager = User::find($site->attributes['site_manager_id']);
            if ($manager) {
                SiteAssignment::recordUserAssignment($site, $manager, 'site_manager', null, Auth::id(), 'Site manager assignment');
            }
        }

        if (Schema::hasTable('project_sites') && $site->project_id) {
            DB::table('project_sites')->updateOrInsert(
                ['project_id' => $site->project_id, 'site_id' => $site->id],
                [
                    'company_id' => $site->company_id,
                    'is_primary' => true,
                    'start_date' => Carbon::today()->format('Y-m-d'),
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ]
            );
        }
    }

    protected function validateSite(Request $request, ?int $companyId, ?int $ignoreSiteId = null): array
    {
        $codeUniqueRule = Rule::unique('sites', 'code')->where(fn ($q) => $q->where('company_id', $companyId));
        if ($ignoreSiteId !== null) {
            $codeUniqueRule->ignore($ignoreSiteId);
        }

        return $request->validate([
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                $codeUniqueRule,
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,paused,closed'],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)->where('status', 'active')),
            ],
            'site_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)->where('status', 'active')),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_m' => ['nullable', 'integer', 'min:20', 'max:10000'],
            'shift_start' => ['nullable', 'string', 'max:8'],
            'shift_end' => ['nullable', 'string', 'max:8'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    protected function filterPersistableColumns(array $validated): array
    {
        $columns = Schema::getColumnListing('sites');
        if (isset($validated['geofence_radius_m']) && $validated['geofence_radius_m'] === null) {
            $validated['geofence_radius_m'] = 200;
        }

        return collect($validated)->only($columns)->all();
    }

    protected function getCompanySupervisors()
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
