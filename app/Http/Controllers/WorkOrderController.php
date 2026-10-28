<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Services\Operations\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function __construct(protected WorkOrderService $workOrderService)
    {
    }

    public function index(Request $request): View
    {
        $query = WorkOrder::with(['project', 'site', 'creator', 'siteManager', 'seniorManager']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('order_type', $request->query('type'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }

        $workOrders = $query->latest()->paginate(15);
        $sites = Site::where('status', 'active')->get();

        return view('work_orders.index', compact('workOrders', 'sites'));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkOrder::class);

        $projects = Project::where('status', 'active')->get();
        $sites = Site::where('status', 'active')->get();

        return view('work_orders.create', compact('projects', 'sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', WorkOrder::class);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'order_type' => ['required', 'in:client_issued,internal'],
            'client_reference' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope_of_work' => ['nullable', 'string'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'target_start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:target_start_date'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,png'],
        ]);

        $user = Auth::user();
        $wo = $this->workOrderService->createWorkOrder($validated, $user);

        // Optional document attachment
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('work_orders/' . $wo->id, 'public');

            WorkOrderAttachment::create([
                'work_order_id' => $wo->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $user->id,
            ]);
        }

        return redirect()->route('work_orders.show', $wo)->with('success', "Work order {$wo->order_number} submitted for Tier 1 approval.");
    }

    public function show(WorkOrder $workOrder): View
    {
        Gate::authorize('view', $workOrder);

        $workOrder->load(['project', 'site', 'creator', 'siteManager', 'seniorManager', 'approvals.actor', 'attachments.uploader']);

        return view('work_orders.show', compact('workOrder'));
    }

    public function approveSite(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('approveSite', $workOrder);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workOrderService->approveBySiteManager($workOrder, Auth::user(), $validated['notes'] ?? null);

        return back()->with('success', "Work order {$workOrder->order_number} approved by Site Manager and forwarded to Senior Management.");
    }

    public function approveSenior(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('approveSenior', $workOrder);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workOrderService->approveBySeniorManager($workOrder, Auth::user(), $validated['notes'] ?? null);

        return back()->with('success', "Work order {$workOrder->order_number} fully authorized by Senior Management for execution.");
    }

    public function reject(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('reject', $workOrder);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->workOrderService->rejectWorkOrder($workOrder, Auth::user(), $validated['rejection_reason']);

        return back()->with('success', "Work order {$workOrder->order_number} has been rejected.");
    }
}
