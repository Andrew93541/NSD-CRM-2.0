<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\Operations\NotificationDispatcher;
use App\Services\Procurement\InventoryService;
use App\Services\Security\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    protected function isGlobalProcurementViewer(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin'])) {
            return true;
        }

        if ($user->isRestrictedToAssignedSites()) {
            return false;
        }

        return $user->hasRole(['senior_manager', 'warehouse_manager', 'office_staff']);
    }

    public function requests(): View
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalProcurementViewer() ? null : $user->accessibleSiteIds();

        $requests = PurchaseRequest::with(['site', 'warehouse', 'requester', 'approver', 'items.material'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->latest()
            ->paginate(15);

        return view('procurement.requests', compact('requests'));
    }

    public function createRequest(): View
    {
        Gate::authorize('create', PurchaseRequest::class);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalProcurementViewer() ? null : $user->accessibleSiteIds();

        $sites = Site::where('status', 'active')
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('id', $accessibleSiteIds))
            ->get();

        $warehouses = Warehouse::where('is_active', true)
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->get();

        $materials = Material::where('is_active', true)->get();

        return view('procurement.create_request', compact('sites', 'warehouses', 'materials'));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        Gate::authorize('create', PurchaseRequest::class);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        if (! $request->filled('urgency')) {
            $request->merge([
                'urgency' => $request->boolean('is_emergency') ? 'emergency' : 'normal',
            ]);
        } elseif ($request->boolean('is_emergency')) {
            $request->merge(['urgency' => 'emergency']);
        }

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'urgency' => ['required', 'in:normal,urgent,emergency'],
            'is_emergency' => ['boolean'],
            'emergency_justification' => ['required_if:is_emergency,1', 'nullable', 'string', 'min:10'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => [
                'required',
                Rule::exists('materials', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.purpose_notes' => ['nullable', 'string'],
        ]);

        if (! $this->isGlobalProcurementViewer()) {
            if (! empty($validated['site_id']) && ! $user->isAssignedToSite((int) $validated['site_id'])) {
                abort(403, 'You are not authorized to request materials for this site.');
            }

            if (! empty($validated['warehouse_id'])) {
                $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);
                if ($warehouse->site_id && ! $user->isAssignedToSite((int) $warehouse->site_id)) {
                    abort(403, 'You are not authorized to request materials for this site warehouse.');
                }
            }
        }

        try {
            $pr = $this->inventoryService->createPurchaseRequest($validated, $validated['items'], $user);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        AuditLog::record(
            'procurement.request.created',
            $pr,
            null,
            ['request_number' => $pr->request_number, 'site_id' => $pr->site_id, 'urgency' => $pr->urgency],
            "Created purchase request {$pr->request_number}"
        );

        NotificationDispatcher::notifyApprovers(
            companyId: (int) $companyId,
            permission: 'procurement.approve',
            fallbackRoles: ['super_admin', 'company_admin', 'senior_manager', 'site_manager'],
            type: 'pr_pending_approval',
            title: "Purchase Request {$pr->request_number} Awaiting Approval",
            message: "{$user->name} submitted purchase request {$pr->request_number} ({$pr->urgency} urgency).",
            actionUrl: route('procurement.requests'),
            siteId: $pr->site_id ? (int) $pr->site_id : null,
            excludeUserIds: [(int) $user->id]
        );

        return redirect()->route('procurement.requests')->with('success', "Purchase request {$pr->request_number} created.");
    }

    public function approveRequest(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('approve', $purchaseRequest);

        $validated = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        try {
            $pr = $this->inventoryService->approvePurchaseRequest($purchaseRequest, Auth::user(), $validated['comments'] ?? null, true);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record(
            'procurement.request.approved',
            $pr,
            ['status' => 'pending'],
            ['status' => 'approved', 'approved_by' => Auth::id()],
            "Approved purchase request {$pr->request_number}"
        );

        if ($pr->requested_by && (int) $pr->requested_by !== (int) Auth::id()) {
            NotificationDispatcher::notifyUserDeduplicated(
                userId: (int) $pr->requested_by,
                type: 'pr_approved',
                title: "Purchase Request {$pr->request_number} Approved",
                message: 'Your purchase request '.$pr->request_number.' was approved by '.Auth::user()->name.'.',
                actionUrl: route('procurement.requests'),
                siteId: $pr->site_id ? (int) $pr->site_id : null,
                companyId: (int) $pr->company_id
            );
        }

        return back()->with('success', "Purchase request {$purchaseRequest->request_number} approved.");
    }

    public function rejectRequest(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('reject', $purchaseRequest);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $pr = $this->inventoryService->rejectPurchaseRequest($purchaseRequest, Auth::user(), $validated['rejection_reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record(
            'procurement.request.rejected',
            $pr,
            ['status' => 'pending'],
            [
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'rejection_reason' => $pr->rejection_reason,
            ],
            "Rejected purchase request {$pr->request_number}"
        );

        if ($pr->requested_by && (int) $pr->requested_by !== (int) Auth::id()) {
            NotificationDispatcher::notifyUserDeduplicated(
                userId: (int) $pr->requested_by,
                type: 'pr_rejected',
                title: "Purchase Request {$pr->request_number} Rejected",
                message: 'Your purchase request '.$pr->request_number.' was rejected: '.$pr->rejection_reason,
                actionUrl: route('procurement.requests'),
                siteId: $pr->site_id ? (int) $pr->site_id : null,
                companyId: (int) $pr->company_id
            );
        }

        return back()->with('success', "Purchase request {$purchaseRequest->request_number} rejected.");
    }

    public function cancelRequest(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('cancel', $purchaseRequest);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $pr = $this->inventoryService->cancelPurchaseRequest($purchaseRequest, Auth::user(), $validated['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record(
            'procurement.request.cancelled',
            $pr,
            ['status' => 'pending'],
            ['status' => 'cancelled', 'cancelled_by' => Auth::id()],
            "Cancelled purchase request {$pr->request_number}"
        );

        return back()->with('success', "Purchase request {$purchaseRequest->request_number} cancelled.");
    }

    public function createOrderForm(PurchaseRequest $purchaseRequest): View
    {
        Gate::authorize('createOrder', $purchaseRequest);
        $purchaseRequest->load(['items.material', 'site', 'warehouse']);
        $vendors = Schema::hasTable('vendors')
            ? Vendor::where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('procurement.create_po', compact('purchaseRequest', 'vendors'));
    }

    public function storeOrder(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('createOrder', $purchaseRequest);

        $companyId = (int) TenantContext::getCompanyId();

        $rules = [
            'supplier_name' => ['required_without:vendor_id', 'nullable', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:30'],
            'expected_delivery_date' => ['nullable', 'date'],
            'unit_prices' => ['required', 'array', 'min:1'],
            'unit_prices.*' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];

        if (Schema::hasTable('vendors')) {
            $rules['vendor_id'] = [
                'nullable',
                Rule::exists('vendors', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['vendor_id']) && Schema::hasTable('vendors')) {
            $vendor = Vendor::findOrFail((int) $validated['vendor_id']);
            if (empty($validated['supplier_name'])) {
                $validated['supplier_name'] = $vendor->name;
            }
            if (empty($validated['supplier_phone']) && ! empty($vendor->phone)) {
                $validated['supplier_phone'] = mb_substr((string) $vendor->phone, 0, 30);
            }
        }

        try {
            $po = $this->inventoryService->createPurchaseOrder($purchaseRequest, $validated, Auth::user());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record(
            'procurement.order.created',
            $po,
            null,
            ['po_number' => $po->po_number, 'total_amount' => $po->total_amount, 'supplier_name' => $po->supplier_name],
            "Issued purchase order {$po->po_number} for {$purchaseRequest->request_number}"
        );

        return redirect()->route('procurement.orders')->with('success', "Purchase Order {$po->po_number} generated.");
    }

    public function orders(): View
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalProcurementViewer() ? null : $user->accessibleSiteIds();

        $orders = PurchaseOrder::with(['site', 'warehouse', 'creator', 'items.material'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->latest()
            ->paginate(15);

        return view('procurement.orders', compact('orders'));
    }

    public function downloadOrderPdf(PurchaseOrder $purchaseOrder): StreamedResponse
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $user = Auth::user();
        if ((int) $purchaseOrder->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        if (! $this->isGlobalProcurementViewer()) {
            if ($purchaseOrder->site_id && ! $user->isAssignedToSite((int) $purchaseOrder->site_id)) {
                abort(403, 'You are not authorized to download purchase orders for another site.');
            }
            if (! $purchaseOrder->site_id && $purchaseOrder->warehouse && $purchaseOrder->warehouse->site_id && ! $user->isAssignedToSite((int) $purchaseOrder->warehouse->site_id)) {
                abort(403, 'You are not authorized to download purchase orders for another site warehouse.');
            }
        }

        $relations = ['items.material', 'site', 'warehouse', 'purchaseRequest', 'creator', 'company'];
        if (Schema::hasTable('vendors') && Schema::hasColumn('purchase_orders', 'vendor_id')) {
            $relations[] = 'vendor';
        }
        $purchaseOrder->load($relations);

        $html = view('procurement.pdf.purchase_order', [
            'order' => $purchaseOrder,
            'company' => $purchaseOrder->company,
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfBytes = $dompdf->output();

        AuditLog::record(
            'procurement.order.pdf_exported',
            $purchaseOrder,
            null,
            ['po_number' => $purchaseOrder->po_number],
            "Exported printable PDF for Purchase Order {$purchaseOrder->po_number}"
        );

        $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $purchaseOrder->po_number).'.pdf';

        return response()->streamDownload(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function receiveGoodsForm(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizeGoodsReceipt($purchaseOrder);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalProcurementViewer() ? null : $user->accessibleSiteIds();

        $purchaseOrder->load(['items.material', 'site', 'warehouse']);
        $warehouses = Warehouse::where('is_active', true)
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->get();

        return view('procurement.receive_goods', compact('purchaseOrder', 'warehouses'));
    }

    public function confirmReceipt(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeGoodsReceipt($purchaseOrder);

        $user = Auth::user();
        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate([
            'destination_warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'received' => ['required', 'array', 'min:1'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
            'challan_or_invoice_no' => ['nullable', 'string', 'max:100'],
        ]);

        $destWarehouse = Warehouse::findOrFail((int) $validated['destination_warehouse_id']);
        if (! $this->isGlobalProcurementViewer() && $destWarehouse->site_id && ! $user->isAssignedToSite((int) $destWarehouse->site_id)) {
            abort(403, 'You are not authorized to receive goods into an unassigned site warehouse.');
        }

        try {
            $tx = $this->inventoryService->recordGoodsReceipt(
                $purchaseOrder,
                (int) $validated['destination_warehouse_id'],
                $validated['received'],
                $user,
                $validated['challan_or_invoice_no'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['received' => $e->getMessage()]);
        }

        AuditLog::record(
            'procurement.receipt.confirmed',
            $tx,
            null,
            ['transaction_number' => $tx->transaction_number, 'purchase_order_id' => $purchaseOrder->id, 'warehouse_id' => $destWarehouse->id],
            "Confirmed goods receipt {$tx->transaction_number} for {$purchaseOrder->po_number}"
        );

        return redirect()->route('procurement.orders')->with('success', "Goods receipt {$tx->transaction_number} confirmed and stock updated.");
    }

    protected function authorizeGoodsReceipt(PurchaseOrder $purchaseOrder): void
    {
        $user = Auth::user();
        if (! $user || (int) $purchaseOrder->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        $site = $purchaseOrder->site_id
            ? Site::find($purchaseOrder->site_id)
            : ($purchaseOrder->warehouse?->site_id ? Site::find($purchaseOrder->warehouse->site_id) : null);

        if (! $user->can('receive', [InventoryStock::class, $site])) {
            abort(403, 'You are not authorized to receive goods for this purchase order or site.');
        }
    }

    protected function canManageVendors(): bool
    {
        $user = Auth::user();
        if (! $user || $user->isExplicitlyDenied('procurement.manage')) {
            return false;
        }

        return $user->canPerform('procurement.manage', ['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager'])
            || $user->hasPermission('procurement.approve');
    }

    public function vendors(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $canManage = $this->canManageVendors();
        $vendorTypes = Vendor::VENDOR_TYPES;

        if (! Schema::hasTable('vendors')) {
            $vendors = collect();

            return view('procurement.vendors', compact('vendors', 'canManage', 'vendorTypes'));
        }

        $query = Vendor::query();
        if (Schema::hasColumn('purchase_orders', 'vendor_id')) {
            $query->withCount('purchaseOrders');
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('gstin', 'like', $search);
            });
        }

        $vendors = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('procurement.vendors', compact('vendors', 'canManage', 'vendorTypes'));
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        if (! $this->canManageVendors()) {
            abort(403, 'Unauthorized to manage supplier directory.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        if ($request->filled('gstin')) {
            $request->merge(['gstin' => strtoupper(trim((string) $request->input('gstin')))]);
        }
        if ($request->filled('pan')) {
            $request->merge(['pan' => strtoupper(trim((string) $request->input('pan')))]);
        }
        if ($request->filled('bank_ifsc')) {
            $request->merge(['bank_ifsc' => strtoupper(trim((string) $request->input('bank_ifsc')))]);
        }

        $validated = $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('vendors', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in(array_keys(Vendor::VENDOR_TYPES))],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'bank_ifsc' => ['nullable', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $vendor = DB::transaction(function () use ($companyId, $validated, $request) {
            $code = $validated['code'] ?? null;
            if (empty($code)) {
                DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
                $code = $this->nextAtomicVendorCode($companyId);
            }

            return Vendor::create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $validated['name'],
                'vendor_type' => $validated['vendor_type'],
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'pan' => $validated['pan'] ?? null,
                'address' => $validated['address'] ?? null,
                'payment_terms_days' => (int) ($validated['payment_terms_days'] ?? 30),
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_no' => $validated['bank_account_no'] ?? null,
                'bank_ifsc' => $validated['bank_ifsc'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        });

        AuditLog::record(
            'procurement.vendor.created',
            $vendor,
            null,
            [
                'code' => $vendor->code,
                'name' => $vendor->name,
                'gstin' => $vendor->gstin,
                'bank_account_no' => $vendor->bank_account_no,
            ],
            "Created supplier {$vendor->name} ({$vendor->code})"
        );

        return redirect()->route('procurement.vendors')->with('success', "Supplier {$vendor->name} ({$vendor->code}) added.");
    }

    public function updateVendor(Request $request, Vendor $vendor): RedirectResponse
    {
        if (! $this->canManageVendors()) {
            abort(403, 'Unauthorized to manage supplier directory.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        if ($request->filled('gstin')) {
            $request->merge(['gstin' => strtoupper(trim((string) $request->input('gstin')))]);
        }
        if ($request->filled('pan')) {
            $request->merge(['pan' => strtoupper(trim((string) $request->input('pan')))]);
        }
        if ($request->filled('bank_ifsc')) {
            $request->merge(['bank_ifsc' => strtoupper(trim((string) $request->input('bank_ifsc')))]);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('vendors', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($vendor->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in(array_keys(Vendor::VENDOR_TYPES))],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'bank_ifsc' => ['nullable', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $vendor->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'vendor_type' => $validated['vendor_type'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'pan' => $validated['pan'] ?? null,
            'address' => $validated['address'] ?? null,
            'payment_terms_days' => (int) ($validated['payment_terms_days'] ?? 30),
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_no' => $validated['bank_account_no'] ?? null,
            'bank_ifsc' => $validated['bank_ifsc'] ?? null,
            'is_active' => $request->boolean('is_active', false),
        ]);

        AuditLog::record(
            'procurement.vendor.updated',
            $vendor,
            null,
            [
                'code' => $vendor->code,
                'name' => $vendor->name,
                'is_active' => $vendor->is_active,
                'bank_account_no' => $vendor->bank_account_no,
            ],
            "Updated supplier {$vendor->name} ({$vendor->code})"
        );

        return redirect()->route('procurement.vendors')->with('success', "Supplier {$vendor->name} updated.");
    }

    protected function nextAtomicVendorCode(int $companyId): string
    {
        $maxExisting = 0;
        $codes = Vendor::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', 'like', 'VEND-%')
            ->pluck('code');

        foreach ($codes as $existingCode) {
            if (preg_match('/^VEND-(\d+)$/', (string) $existingCode, $m)) {
                $maxExisting = max($maxExisting, (int) $m[1]);
            }
        }

        $nextNumber = $maxExisting + 1;

        if (Schema::hasTable('worker_sequences')) {
            $seqRow = DB::table('worker_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', 'VEND')
                ->where('year', 0)
                ->lockForUpdate()
                ->first();

            if ($seqRow) {
                $nextNumber = max((int) $seqRow->last_number, $maxExisting) + 1;
                DB::table('worker_sequences')
                    ->where('id', $seqRow->id)
                    ->update(['last_number' => $nextNumber, 'updated_at' => now()]);
            } else {
                DB::table('worker_sequences')->insert([
                    'company_id' => $companyId,
                    'prefix' => 'VEND',
                    'year' => 0,
                    'last_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        while (Vendor::withoutGlobalScopes()->where('company_id', $companyId)->where('code', sprintf('VEND-%03d', $nextNumber))->exists()) {
            $nextNumber++;
        }

        return sprintf('VEND-%03d', $nextNumber);
    }

    public function destroyVendor(Vendor $vendor): RedirectResponse
    {
        if (! $this->canManageVendors()) {
            abort(403, 'Unauthorized to manage supplier directory.');
        }

        if (Schema::hasColumn('purchase_orders', 'vendor_id') && $vendor->purchaseOrders()->exists()) {
            return back()->withErrors([
                'vendor' => "Cannot delete supplier {$vendor->name} because purchase orders reference it. Mark it Inactive instead.",
            ]);
        }

        $code = $vendor->code;
        $name = $vendor->name;
        $vendor->delete();

        AuditLog::record(
            'procurement.vendor.deleted',
            null,
            ['code' => $code, 'name' => $name],
            null,
            "Deleted unreferenced supplier {$name} ({$code})"
        );

        return redirect()->route('procurement.vendors')->with('success', "Supplier {$name} deleted.");
    }
}
