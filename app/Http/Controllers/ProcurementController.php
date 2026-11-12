<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Warehouse;
use App\Services\Procurement\InventoryService;
use App\Services\Security\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService)
    {
    }

    protected function isGlobalProcurementViewer(): bool
    {
        $user = Auth::user();
        if (!$user) {
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

        if (!$request->filled('urgency')) {
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

        if (!$this->isGlobalProcurementViewer()) {
            if (!empty($validated['site_id']) && !$user->isAssignedToSite((int) $validated['site_id'])) {
                abort(403, 'You are not authorized to request materials for this site.');
            }

            if (!empty($validated['warehouse_id'])) {
                $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);
                if ($warehouse->site_id && !$user->isAssignedToSite((int) $warehouse->site_id)) {
                    abort(403, 'You are not authorized to request materials for this site warehouse.');
                }
            }
        }

        try {
            $pr = $this->inventoryService->createPurchaseRequest($validated, $validated['items'], $user);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        \App\Models\AuditLog::record(
            'procurement.request.created',
            $pr,
            null,
            ['request_number' => $pr->request_number, 'site_id' => $pr->site_id, 'urgency' => $pr->urgency],
            "Created purchase request {$pr->request_number}"
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
            $pr = $this->inventoryService->approvePurchaseRequest($purchaseRequest, Auth::user(), $validated['comments'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        \App\Models\AuditLog::record(
            'procurement.request.approved',
            $pr,
            ['status' => 'pending'],
            ['status' => 'approved', 'approved_by' => Auth::id()],
            "Approved purchase request {$pr->request_number}"
        );

        return back()->with('success', "Purchase request {$purchaseRequest->request_number} approved.");
    }

    public function createOrderForm(PurchaseRequest $purchaseRequest): View
    {
        Gate::authorize('createOrder', $purchaseRequest);
        $purchaseRequest->load(['items.material', 'site', 'warehouse']);

        return view('procurement.create_po', compact('purchaseRequest'));
    }

    public function storeOrder(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('createOrder', $purchaseRequest);

        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:50'],
            'expected_delivery_date' => ['nullable', 'date'],
            'unit_prices' => ['required', 'array', 'min:1'],
            'unit_prices.*' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $po = $this->inventoryService->createPurchaseOrder($purchaseRequest, $validated, Auth::user());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        \App\Models\AuditLog::record(
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
        if (!$this->isGlobalProcurementViewer() && $destWarehouse->site_id && !$user->isAssignedToSite((int) $destWarehouse->site_id)) {
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

        \App\Models\AuditLog::record(
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
        if (!$user || (int) $purchaseOrder->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        $site = $purchaseOrder->site_id
            ? Site::find($purchaseOrder->site_id)
            : ($purchaseOrder->warehouse?->site_id ? Site::find($purchaseOrder->warehouse->site_id) : null);

        if (!$user->can('receive', [InventoryStock::class, $site])) {
            abort(403, 'You are not authorized to receive goods for this purchase order or site.');
        }
    }
}
