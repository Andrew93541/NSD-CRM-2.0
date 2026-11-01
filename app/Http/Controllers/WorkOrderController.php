<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderRevision;
use App\Services\Operations\WorkOrderService;
use App\Services\Security\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderController extends Controller
{
    public const ALLOWED_ATTACHMENT_MIMES = 'pdf,doc,docx,jpg,jpeg,png,webp';
    public const MAX_ATTACHMENT_SIZE_KB = 10240; // 10 MB

    public function __construct(protected WorkOrderService $workOrderService)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkOrder::class);

        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            || ($user->hasRole('office_staff') && !$user->sites()->exists());

        $query = WorkOrder::with(['client', 'project', 'site', 'creator', 'siteManager', 'seniorManager', 'items', 'raBills']);

        if (!$hasAllSiteAccess) {
            $accessibleSiteIds = $user->accessibleSiteIds();
            $query->where(function ($q) use ($accessibleSiteIds) {
                $q->whereNull('site_id')
                  ->orWhereIn('site_id', $accessibleSiteIds);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('order_type', $request->query('type'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        if ($request->filled('site_id')) {
            $siteId = (int) $request->query('site_id');
            if (!$hasAllSiteAccess && !$user->isAssignedToSite($siteId)) {
                abort(403, 'Unauthorized to filter work orders for an unassigned site.');
            }
            $query->where('site_id', $siteId);
        }

        $workOrders = $query->latest()->paginate(15)->withQueryString();
        $sites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : Site::where('status', 'active')->whereIn('id', $user->accessibleSiteIds())->orderBy('code')->get();
        $clients = Schema::hasTable('clients')
            ? Client::where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('work_orders.index', compact('workOrders', 'sites', 'clients'));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkOrder::class);

        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            || ($user->hasRole('office_staff') && !$user->sites()->exists());

        $projects = Project::where('status', 'active')->get();
        $sites = $hasAllSiteAccess
            ? Site::where('status', 'active')->get()
            : Site::where('status', 'active')->whereIn('id', $user->accessibleSiteIds())->get();
        $clients = Schema::hasTable('clients')
            ? Client::where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('work_orders.create', compact('projects', 'sites', 'clients'));
    }

    public function storeClient(Request $request): RedirectResponse
    {
        Gate::authorize('create', WorkOrder::class);

        $companyId = (int) TenantContext::getCompanyId();
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'vendor_code' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $client = Client::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_active' => true,
        ]));

        return back()->with('success', "Client '{$client->name}' registered.");
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', WorkOrder::class);

        $companyId = (int) TenantContext::getCompanyId();
        $user = Auth::user();

        $validated = $request->validate([
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'site_id' => [
                'nullable',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'client_name' => ['nullable', 'string', 'max:200'],
            'client_gstin' => ['nullable', 'string', 'max:30'],
            'client_address' => ['nullable', 'string', 'max:1000'],
            'order_type' => ['required', 'in:client_issued,internal'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'user_department_person' => ['nullable', 'string', 'max:150'],
            'effective_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'client_reference' => ['nullable', 'string', 'max:100'],
            'contractor_name' => ['nullable', 'string', 'max:150'],
            'contractor_gst' => ['nullable', 'string', 'max:40'],
            'contractor_contact' => ['nullable', 'string', 'max:80'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'cgst_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'sgst_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope_of_work' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.sr_no' => ['nullable', 'string', 'max:30'],
            'items.*.service_code' => ['nullable', 'string', 'max:60'],
            'items.*.sac_code' => ['nullable', 'string', 'max:30'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.specification' => ['nullable', 'string', 'max:5000'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.completion_date' => ['nullable', 'date'],
            'boq_items' => ['nullable', 'array'],
            'boq_items.*.sr_no' => ['nullable', 'string', 'max:30'],
            'boq_items.*.service_code' => ['nullable', 'string', 'max:60'],
            'boq_items.*.sac_code' => ['nullable', 'string', 'max:30'],
            'boq_items.*.description' => ['nullable', 'string', 'max:500'],
            'boq_items.*.specification' => ['nullable', 'string', 'max:5000'],
            'boq_items.*.unit' => ['nullable', 'string', 'max:30'],
            'boq_items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'boq_items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0'],
            'boq_items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'target_start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:target_start_date'],
            'attachment' => ['nullable', 'file', 'max:' . self::MAX_ATTACHMENT_SIZE_KB, 'mimes:' . self::ALLOWED_ATTACHMENT_MIMES],
        ]);

        if (!empty($validated['site_id'])) {
            $siteId = (int) $validated['site_id'];
            $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
                || ($user->hasRole('office_staff') && !$user->sites()->exists());
            if (!$hasAllSiteAccess && !$user->isAssignedToSite($siteId)) {
                abort(403, 'You are not authorized to create a work order for this site.');
            }
        }

        if (empty($validated['client_id']) && !empty($validated['client_name']) && Schema::hasTable('clients')) {
            $client = Client::firstOrCreate(
                ['company_id' => $companyId, 'name' => trim($validated['client_name'])],
                [
                    'gstin' => $validated['client_gstin'] ?? null,
                    'address' => $validated['client_address'] ?? null,
                    'is_active' => true,
                ]
            );
            $validated['client_id'] = $client->id;
        }

        $wo = $this->workOrderService->createWorkOrder($validated, $user);

        // Private document attachment storage
        if ($request->hasFile('attachment')) {
            $this->persistWorkOrderAttachment($wo, $request->file('attachment'), $user->id);
        }

        return redirect()->route('work_orders.show', $wo)->with('success', "Work order {$wo->order_number} submitted for Tier 1 approval.");
    }

    public function show(WorkOrder $workOrder): View
    {
        Gate::authorize('view', $workOrder);

        $workOrder->load([
            'client',
            'project',
            'site',
            'creator',
            'siteManager',
            'seniorManager',
            'items',
            'revisions.changer',
            'revisions.approver',
            'measurements.workOrderItem',
            'measurements.recorder',
            'raBills.items',
            'approvals.actor',
            'attachments.uploader',
        ]);

        return view('work_orders.show', compact('workOrder'));
    }

    public function printOrder(WorkOrder $workOrder): View
    {
        Gate::authorize('view', $workOrder);

        $workOrder->load(['company', 'client', 'project', 'site', 'creator', 'siteManager', 'seniorManager', 'items', 'approvals.actor']);

        return view('work_orders.print', compact('workOrder'));
    }

    public function exportPdf(Request $request, WorkOrder $workOrder): StreamedResponse
    {
        Gate::authorize('view', $workOrder);

        $export = app(\App\Services\Operations\RaBillExportService::class)->exportWorkOrderPdf(
            $workOrder,
            $request->user()
        );

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }

    public function storeRevision(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('createRevision', $workOrder);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.sr_no' => ['required', 'string', 'max:30'],
            'items.*.service_code' => ['nullable', 'string', 'max:60'],
            'items.*.sac_code' => ['nullable', 'string', 'max:30'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.specification' => ['nullable', 'string', 'max:5000'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.ordered_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.completion_date' => ['nullable', 'date'],
        ]);

        $revision = $this->workOrderService->createRevision(
            $workOrder,
            $validated['items'],
            $validated['reason'],
            Auth::user(),
            false
        );

        return back()->with('success', "Work Order Revision #{$revision->revision_number} submitted and awaiting Senior Manager approval.");
    }

    public function approveRevision(Request $request, WorkOrder $workOrder, int $revision): RedirectResponse
    {
        $rev = WorkOrderRevision::where('work_order_id', $workOrder->id)->findOrFail($revision);

        Gate::authorize('approveRevision', [$workOrder, $rev]);

        $this->workOrderService->approveRevision($workOrder, $rev, Auth::user());

        return back()->with('success', "Work Order Revision #{$rev->revision_number} approved and applied.");
    }

    public function storeAttachment(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('uploadAttachment', $workOrder);

        $request->validate([
            'attachment' => ['required', 'file', 'max:' . self::MAX_ATTACHMENT_SIZE_KB, 'mimes:' . self::ALLOWED_ATTACHMENT_MIMES],
        ]);

        $att = $this->persistWorkOrderAttachment($workOrder, $request->file('attachment'), (int) Auth::id());

        AuditLog::record(
            'work_order.attachment_uploaded',
            $workOrder,
            null,
            ['attachment_id' => $att->id, 'file_name' => $att->file_name],
            "Uploaded attachment '{$att->file_name}' to work order {$workOrder->order_number}"
        );

        return back()->with('success', "Attachment '{$att->file_name}' uploaded securely.");
    }

    public function downloadAttachment(WorkOrder $workOrder, int $attachment): StreamedResponse
    {
        $att = WorkOrderAttachment::where('work_order_id', $workOrder->id)->findOrFail($attachment);

        Gate::authorize('downloadAttachment', [$workOrder, $att]);

        if (!$this->isValidAttachmentPath($workOrder, $att)) {
            abort(403, 'Invalid work order attachment storage path.');
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($att->file_path)) {
            abort(404, 'The requested work order attachment could not be found in private storage.');
        }

        $safeDownloadName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', basename($att->file_name)) ?: 'work-order-attachment';

        return $disk->download($att->file_path, $safeDownloadName, [
            'Content-Type' => $att->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyAttachment(WorkOrder $workOrder, int $attachment): RedirectResponse
    {
        $att = WorkOrderAttachment::where('work_order_id', $workOrder->id)->findOrFail($attachment);

        Gate::authorize('deleteAttachment', [$workOrder, $att]);

        if ($this->isValidAttachmentPath($workOrder, $att)) {
            $disk = Storage::disk('local');
            if ($disk->exists($att->file_path)) {
                $disk->delete($att->file_path);
            }
        }

        $fileName = $att->file_name;
        $att->delete();

        AuditLog::record(
            'work_order.attachment_deleted',
            $workOrder,
            ['file_name' => $fileName],
            null,
            "Deleted attachment '{$fileName}' from work order {$workOrder->order_number}"
        );

        return back()->with('success', "Attachment '{$fileName}' deleted.");
    }

    protected function persistWorkOrderAttachment(WorkOrder $wo, \Illuminate\Http\UploadedFile $file, int $uploadedBy): WorkOrderAttachment
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $directory = sprintf('work-orders/%d/%d', $wo->company_id, $wo->id);
        $storedName = Str::uuid()->toString() . '.' . $ext;
        $storedPath = $file->storeAs($directory, $storedName, 'local');

        return WorkOrderAttachment::create([
            'work_order_id' => $wo->id,
            'file_name' => basename($file->getClientOriginalName()),
            'file_path' => $storedPath,
            'file_size' => (int) $file->getSize(),
            'mime_type' => (string) $file->getMimeType(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    protected function isValidAttachmentPath(WorkOrder $wo, WorkOrderAttachment $att): bool
    {
        $path = str_replace('\\', '/', $att->file_path);
        if (str_contains($path, '..')) {
            return false;
        }

        $expectedPrefix = sprintf('work-orders/%d/%d/', $wo->company_id, $wo->id);
        $legacyPrefix = sprintf('work_orders/%d/', $wo->id);

        return str_starts_with($path, $expectedPrefix) || str_starts_with($path, $legacyPrefix);
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
