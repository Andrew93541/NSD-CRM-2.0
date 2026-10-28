<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Warehouse;
use App\Services\Procurement\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService)
    {
    }

    public function requests(): View
    {
        $requests = PurchaseRequest::with(['site', 'warehouse', 'requester', 'approver', 'items.material'])
            ->latest()
            ->paginate(15);

        return view('procurement.requests', compact('requests'));
    }

    public function createRequest(): View
    {
        $sites = Site::where('status', 'active')->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        $materials = Material::where('is_active', true)->get();

        return view('procurement.create_request', compact('sites', 'warehouses', 'materials'));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['nullable', 'exists:sites,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'urgency' => ['required', 'in:normal,urgent,emergency'],
            'is_emergency' => ['boolean'],
            'emergency_justification' => ['required_if:is_emergency,1', 'nullable', 'string', 'min:10'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.purpose_notes' => ['nullable', 'string'],
        ]);

        $pr = $this->inventoryService->createPurchaseRequest($validated, $validated['items'], Auth::user());

        return redirect()->route('procurement.requests')->with('success', "Purchase request {$pr->request_number} created.");
    }

    public function approveRequest(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('approve', $purchaseRequest);

        $validated = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        $this->inventoryService->approvePurchaseRequest($purchaseRequest, Auth::user(), $validated['comments'] ?? null);

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
            'unit_prices' => ['required', 'array'],
            'notes' => ['nullable', 'string'],
        ]);

        $po = $this->inventoryService->createPurchaseOrder($purchaseRequest, $validated, Auth::user());

        return redirect()->route('procurement.orders')->with('success', "Purchase Order {$po->po_number} generated.");
    }

    public function orders(): View
    {
        $orders = PurchaseOrder::with(['site', 'warehouse', 'creator', 'items.material'])
            ->latest()
            ->paginate(15);

        return view('procurement.orders', compact('orders'));
    }

    public function receiveGoodsForm(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.material', 'site', 'warehouse']);
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('procurement.receive_goods', compact('purchaseOrder', 'warehouses'));
    }

    public function confirmReceipt(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'destination_warehouse_id' => ['required', 'exists:warehouses,id'],
            'received' => ['required', 'array'],
            'challan_or_invoice_no' => ['nullable', 'string', 'max:100'],
        ]);

        $tx = $this->inventoryService->recordGoodsReceipt(
            $purchaseOrder,
            (int) $validated['destination_warehouse_id'],
            $validated['received'],
            Auth::user(),
            $validated['challan_or_invoice_no'] ?? null
        );

        return redirect()->route('procurement.orders')->with('success', "Goods receipt {$tx->transaction_number} confirmed and stock updated.");
    }
}
