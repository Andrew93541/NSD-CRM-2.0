<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Site;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Operations\NotificationDispatcher;
use App\Services\Procurement\InventoryService;
use App\Services\Security\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    protected function isGlobalInventoryViewer(): bool
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

        return $user->hasRole(['senior_manager', 'warehouse_manager', 'office_staff'])
            || $user->hasPermission('inventory.manage');
    }

    public function stocks(Request $request): View
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalInventoryViewer() ? null : $user->accessibleSiteIds();

        $warehouses = Warehouse::where('is_active', true)
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = $request->query('warehouse_id');

        if ($selectedWarehouseId) {
            $selectedWarehouse = Warehouse::findOrFail((int) $selectedWarehouseId);
            if ($accessibleSiteIds !== null && $selectedWarehouse->site_id && ! in_array((int) $selectedWarehouse->site_id, $accessibleSiteIds, true)) {
                abort(403, 'You are not authorized to view inventory stocks for this site warehouse.');
            }
        }

        $query = InventoryStock::with(['material.category', 'warehouse'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereHas('warehouse', function ($wq) use ($accessibleSiteIds) {
                    $wq->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            });

        if ($selectedWarehouseId) {
            $query->where('warehouse_id', (int) $selectedWarehouseId);
        }

        $stocks = $query->paginate(20)->withQueryString();

        $sites = Site::where('status', 'active')
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('id', $accessibleSiteIds))
            ->orderBy('code')
            ->get();

        $materials = Material::where('is_active', true)->orderBy('name')->get();

        $workOrders = WorkOrder::whereNotIn('status', ['cancelled', 'rejected'])
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'order_number', 'title', 'site_id']);

        $canIssue = $user->can('issue', InventoryStock::class);
        $canManage = $user->can('manage', InventoryStock::class);
        $canRequestPr = $user->can('create', PurchaseRequest::class);

        $pendingAdjustmentsCount = InventoryTransaction::where('transaction_type', 'stock_adjustment')
            ->where('status', 'pending_confirmation')
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->count();

        return view('inventory.stocks', compact(
            'stocks',
            'warehouses',
            'selectedWarehouseId',
            'sites',
            'materials',
            'workOrders',
            'canIssue',
            'canManage',
            'canRequestPr',
            'pendingAdjustmentsCount'
        ));
    }

    public function transactions(Request $request): View
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalInventoryViewer() ? null : $user->accessibleSiteIds();

        $relations = ['sourceWarehouse', 'destinationWarehouse', 'site', 'performedBy', 'confirmedBy', 'items.material'];
        if (Schema::hasColumn('inventory_transactions', 'work_order_id')) {
            $relations[] = 'workOrder';
        }

        $query = InventoryTransaction::with($relations)
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            });

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $transactions = $query->latest('transaction_date')->latest('id')->paginate(25)->withQueryString();
        $canManage = $user->can('manage', InventoryStock::class);

        return view('inventory.transactions', compact('transactions', 'canManage'));
    }

    public function warehouses(): View
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();
        $accessibleSiteIds = $this->isGlobalInventoryViewer() ? null : $user->accessibleSiteIds();

        $warehouses = Warehouse::with(['site', 'manager'])
            ->withCount('stocks')
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->orderBy('name')
            ->get();

        $sites = Site::where('status', 'active')
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('id', $accessibleSiteIds))
            ->orderBy('code')
            ->get();

        $company = Company::find($companyId);
        $managers = $company
            ? $company->users()->wherePivot('status', 'active')->where('users.is_active', true)->orderBy('users.name')->get()
            : collect();

        $canManage = $user->can('manage', InventoryStock::class);

        return view('inventory.warehouses', compact('warehouses', 'sites', 'managers', 'canManage'));
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('warehouses', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'location_type' => ['required', 'in:central_warehouse,site_yard'],
            'site_id' => [
                'nullable',
                'required_if:location_type,site_yard',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)->where('status', 'active')),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['location_type'] === 'central_warehouse') {
            $validated['site_id'] = null;
        }

        if (! $this->isGlobalInventoryViewer()) {
            if (empty($validated['site_id']) || ! $user->isAssignedToSite((int) $validated['site_id'])) {
                abort(403, 'Site-restricted managers can only create storage yards for assigned sites.');
            }
        }

        $warehouse = Warehouse::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'location_type' => $validated['location_type'],
            'site_id' => $validated['site_id'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::record(
            'inventory.warehouse.created',
            $warehouse,
            null,
            ['code' => $warehouse->code, 'name' => $warehouse->name, 'location_type' => $warehouse->location_type, 'site_id' => $warehouse->site_id],
            "Created storage location {$warehouse->name} ({$warehouse->code})"
        );

        return redirect()->route('inventory.warehouses')->with('success', "Storage location {$warehouse->name} ({$warehouse->code}) created.");
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        if (! $this->isGlobalInventoryViewer()) {
            if (! $warehouse->site_id || ! $user->isAssignedToSite((int) $warehouse->site_id)) {
                abort(403, 'You are not authorized to edit this storage location.');
            }
        }

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('warehouses', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($warehouse->id),
            ],
            'location_type' => ['required', 'in:central_warehouse,site_yard'],
            'site_id' => [
                'nullable',
                'required_if:location_type,site_yard',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)->where('status', 'active')),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['location_type'] === 'central_warehouse') {
            $validated['site_id'] = null;
        }

        if (! $this->isGlobalInventoryViewer()) {
            if (empty($validated['site_id']) || ! $user->isAssignedToSite((int) $validated['site_id'])) {
                abort(403, 'Site-restricted managers can only assign storage yards to their assigned sites.');
            }
        }

        $warehouse->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'location_type' => $validated['location_type'],
            'site_id' => $validated['site_id'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active', false),
        ]);

        AuditLog::record(
            'inventory.warehouse.updated',
            $warehouse,
            null,
            ['code' => $warehouse->code, 'name' => $warehouse->name, 'is_active' => $warehouse->is_active],
            "Updated storage location {$warehouse->name} ({$warehouse->code})"
        );

        return redirect()->route('inventory.warehouses')->with('success', "Storage location {$warehouse->name} updated.");
    }

    public function destroyWarehouse(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $user = Auth::user();
        if (! $this->isGlobalInventoryViewer() && (! $warehouse->site_id || ! $user->isAssignedToSite((int) $warehouse->site_id))) {
            abort(403, 'You are not authorized to delete this storage location.');
        }

        $hasReferences = $warehouse->stocks()->exists()
            || InventoryTransaction::where('source_warehouse_id', $warehouse->id)->orWhere('destination_warehouse_id', $warehouse->id)->exists()
            || PurchaseRequest::where('warehouse_id', $warehouse->id)->exists()
            || PurchaseOrder::where('warehouse_id', $warehouse->id)->exists();

        if ($hasReferences) {
            return back()->withErrors([
                'warehouse' => "Cannot delete {$warehouse->name} because stock records or procurement history reference it. Mark it Inactive instead.",
            ]);
        }

        $code = $warehouse->code;
        $name = $warehouse->name;
        $warehouse->delete();

        AuditLog::record(
            'inventory.warehouse.deleted',
            null,
            ['code' => $code, 'name' => $name],
            null,
            "Deleted empty storage location {$name} ({$code})"
        );

        return redirect()->route('inventory.warehouses')->with('success', "Storage location {$name} removed.");
    }

    public function materials(Request $request): View
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $categories = MaterialCategory::withCount('materials')->orderBy('name')->get();

        $query = Material::with('category')->withCount('stocks');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search);
            });
        }

        $materials = $query->orderBy('name')->paginate(25)->withQueryString();
        $canManage = Auth::user()->can('manage', InventoryStock::class);

        return view('inventory.materials', compact('categories', 'materials', 'canManage'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('material_categories', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $category = MaterialCategory::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'],
        ]);

        AuditLog::record(
            'inventory.category.created',
            $category,
            null,
            ['code' => $category->code, 'name' => $category->name],
            "Created material category {$category->name} ({$category->code})"
        );

        return redirect()->route('inventory.materials')->with('success', "Category {$category->name} created.");
    }

    public function updateCategory(Request $request, MaterialCategory $category): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('material_categories', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($category->id),
            ],
        ]);

        $category->update($validated);

        return redirect()->route('inventory.materials')->with('success', "Category {$category->name} updated.");
    }

    public function destroyCategory(MaterialCategory $category): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        if ($category->materials()->exists()) {
            return back()->withErrors([
                'category' => "Cannot delete category {$category->name} while materials belong to it.",
            ]);
        }

        $name = $category->name;
        $category->delete();

        return redirect()->route('inventory.materials')->with('success', "Category {$name} deleted.");
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('material_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:180'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('materials', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'hsn_code' => ['nullable', 'string', 'max:30'],
            'minimum_stock_level' => ['required', 'numeric', 'min:0'],
            'standard_unit_cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'company_id' => $companyId,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'unit_of_measure' => $validated['unit_of_measure'],
            'minimum_stock_level' => $validated['minimum_stock_level'],
            'standard_unit_cost' => $validated['standard_unit_cost'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if (Schema::hasColumn('materials', 'hsn_code')) {
            $payload['hsn_code'] = $validated['hsn_code'] ?? null;
        }

        $material = Material::create($payload);

        AuditLog::record(
            'inventory.material.created',
            $material,
            null,
            ['code' => $material->code, 'name' => $material->name, 'unit_of_measure' => $material->unit_of_measure],
            "Created catalog material {$material->name} ({$material->code})"
        );

        return redirect()->route('inventory.materials')->with('success', "Material {$material->name} ({$material->code}) added to catalog.");
    }

    public function updateMaterial(Request $request, Material $material): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('material_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:180'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('materials', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($material->id),
            ],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'hsn_code' => ['nullable', 'string', 'max:30'],
            'minimum_stock_level' => ['required', 'numeric', 'min:0'],
            'standard_unit_cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'unit_of_measure' => $validated['unit_of_measure'],
            'minimum_stock_level' => $validated['minimum_stock_level'],
            'standard_unit_cost' => $validated['standard_unit_cost'],
            'is_active' => $request->boolean('is_active', false),
        ];

        if (Schema::hasColumn('materials', 'hsn_code')) {
            $payload['hsn_code'] = $validated['hsn_code'] ?? null;
        }

        $material->update($payload);

        AuditLog::record(
            'inventory.material.updated',
            $material,
            null,
            ['code' => $material->code, 'name' => $material->name, 'is_active' => $material->is_active],
            "Updated catalog material {$material->name} ({$material->code})"
        );

        return redirect()->route('inventory.materials')->with('success', "Material {$material->name} updated.");
    }

    public function destroyMaterial(Material $material): RedirectResponse
    {
        Gate::authorize('manage', InventoryStock::class);

        $hasReferences = $material->stocks()->exists()
            || InventoryTransactionItem::where('material_id', $material->id)->exists()
            || PurchaseRequestItem::where('material_id', $material->id)->exists()
            || PurchaseOrderItem::where('material_id', $material->id)->exists();

        if ($hasReferences) {
            return back()->withErrors([
                'material' => "Cannot delete {$material->name} because stock balances or purchase records reference it. Mark it Inactive instead.",
            ]);
        }

        $code = $material->code;
        $name = $material->name;
        $material->delete();

        AuditLog::record(
            'inventory.material.deleted',
            null,
            ['code' => $code, 'name' => $name],
            null,
            "Deleted unreferenced material {$name} ({$code})"
        );

        return redirect()->route('inventory.materials')->with('success', "Material {$name} deleted.");
    }

    public function storeIssue(Request $request): RedirectResponse
    {
        Gate::authorize('issue', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'source_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'work_order_id' => [
                'nullable',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'challan_or_invoice_no' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_token' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $sourceWarehouse = Warehouse::findOrFail((int) $validated['source_warehouse_id']);
        $site = Site::findOrFail((int) $validated['site_id']);

        if (! $this->isGlobalInventoryViewer()) {
            if (! $user->isAssignedToSite($site)) {
                abort(403, 'You are not authorized to issue stock to an unassigned site.');
            }
            if (! $sourceWarehouse->site_id || ! $user->isAssignedToSite((int) $sourceWarehouse->site_id)) {
                abort(403, 'You are not authorized to issue stock from this storage location.');
            }
        }

        try {
            $tx = $this->inventoryService->recordStockIssue($validated, $validated['items'], $user);

            return redirect()->route('inventory.stocks')->with('success', "Stock issue {$tx->transaction_number} recorded.");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['movement' => $e->getMessage()]);
        }
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        Gate::authorize('issue', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'source_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'destination_warehouse_id' => [
                'required',
                'integer',
                'different:source_warehouse_id',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'challan_or_invoice_no' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_token' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $sourceWarehouse = Warehouse::findOrFail((int) $validated['source_warehouse_id']);
        $destWarehouse = Warehouse::findOrFail((int) $validated['destination_warehouse_id']);

        if (! $this->isGlobalInventoryViewer()) {
            if (! $sourceWarehouse->site_id || ! $user->isAssignedToSite((int) $sourceWarehouse->site_id)) {
                abort(403, 'You are not authorized to transfer stock out of this storage location.');
            }
            if (! $destWarehouse->site_id || ! $user->isAssignedToSite((int) $destWarehouse->site_id)) {
                abort(403, 'You are not authorized to transfer stock into an unassigned storage location.');
            }
        }

        try {
            $tx = $this->inventoryService->recordStockTransfer($validated, $validated['items'], $user);

            return redirect()->route('inventory.stocks')->with('success', "Stock transfer {$tx->transaction_number} completed.");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['movement' => $e->getMessage()]);
        }
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        Gate::authorize('issue', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'destination_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'work_order_id' => [
                'nullable',
                'integer',
                Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'challan_or_invoice_no' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_token' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $site = Site::findOrFail((int) $validated['site_id']);
        $destWarehouse = Warehouse::findOrFail((int) $validated['destination_warehouse_id']);

        if (! $this->isGlobalInventoryViewer()) {
            if (! $user->isAssignedToSite($site)) {
                abort(403, 'You are not authorized to return materials from an unassigned site.');
            }
            if ($destWarehouse->site_id && ! $user->isAssignedToSite((int) $destWarehouse->site_id)) {
                abort(403, 'You are not authorized to return materials to another site storage yard.');
            }
        }

        try {
            $tx = $this->inventoryService->recordStockReturn($validated, $validated['items'], $user);

            return redirect()->route('inventory.stocks')->with('success', "Site material return {$tx->transaction_number} recorded.");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['movement' => $e->getMessage()]);
        }
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        Gate::authorize('issue', InventoryStock::class);

        $user = Auth::user();
        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'adjustment_reason' => ['required', 'string', 'min:5', 'max:255'],
            'challan_or_invoice_no' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_token' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer'],
            'items.*.quantity_delta' => ['required', 'numeric', 'not_in:0'],
        ]);

        $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);

        if (! $this->isGlobalInventoryViewer()) {
            if (! $warehouse->site_id || ! $user->isAssignedToSite((int) $warehouse->site_id)) {
                abort(403, 'You are not authorized to request stock adjustments for this storage location.');
            }
        }

        try {
            $tx = $this->inventoryService->requestStockAdjustment($validated, $validated['items'], $user);

            NotificationDispatcher::notifyApprovers(
                companyId: $companyId,
                permission: 'inventory.manage',
                fallbackRoles: ['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager'],
                type: 'stock_adjustment_pending',
                title: "Stock Adjustment {$tx->transaction_number} Awaiting Approval",
                message: "{$user->name} requested stock adjustment {$tx->transaction_number} at {$warehouse->name} ({$validated['adjustment_reason']}).",
                actionUrl: route('inventory.transactions', ['type' => 'stock_adjustment', 'status' => 'pending_confirmation']),
                siteId: $warehouse->site_id ? (int) $warehouse->site_id : null,
                excludeUserIds: [(int) $user->id]
            );

            return redirect()->route('inventory.transactions', ['type' => 'stock_adjustment'])->with(
                'success',
                "Stock adjustment {$tx->transaction_number} submitted for manager approval."
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['movement' => $e->getMessage()]);
        }
    }

    public function approveAdjustment(InventoryTransaction $transaction): RedirectResponse
    {
        Gate::authorize('approveAdjustment', [InventoryStock::class, $transaction]);

        try {
            $tx = $this->inventoryService->approveStockAdjustment($transaction, Auth::user());

            if ($tx->performed_by && (int) $tx->performed_by !== (int) Auth::id()) {
                NotificationDispatcher::notifyUserDeduplicated(
                    userId: (int) $tx->performed_by,
                    type: 'stock_adjustment_approved',
                    title: "Stock Adjustment {$tx->transaction_number} Approved",
                    message: 'Your stock adjustment '.$tx->transaction_number.' was approved by '.Auth::user()->name.'.',
                    actionUrl: route('inventory.transactions', ['type' => 'stock_adjustment']),
                    siteId: $tx->site_id ? (int) $tx->site_id : null,
                    companyId: (int) $tx->company_id
                );
            }

            return back()->with('success', "Stock adjustment {$tx->transaction_number} approved and stock balances updated.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }
    }

    public function rejectAdjustment(Request $request, InventoryTransaction $transaction): RedirectResponse
    {
        Gate::authorize('approveAdjustment', [InventoryStock::class, $transaction]);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $tx = $this->inventoryService->rejectStockAdjustment($transaction, Auth::user(), $validated['rejection_reason']);

            if ($tx->performed_by && (int) $tx->performed_by !== (int) Auth::id()) {
                NotificationDispatcher::notifyUserDeduplicated(
                    userId: (int) $tx->performed_by,
                    type: 'stock_adjustment_rejected',
                    title: "Stock Adjustment {$tx->transaction_number} Rejected",
                    message: 'Your stock adjustment '.$tx->transaction_number.' was rejected: '.$tx->rejection_reason,
                    actionUrl: route('inventory.transactions', ['type' => 'stock_adjustment']),
                    siteId: $tx->site_id ? (int) $tx->site_id : null,
                    companyId: (int) $tx->company_id
                );
            }

            return back()->with('success', "Stock adjustment {$tx->transaction_number} rejected.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }
    }

    public function downloadGrnPdf(InventoryTransaction $transaction): StreamedResponse
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $user = Auth::user();
        if ((int) $transaction->company_id !== (int) TenantContext::getCompanyId()) {
            abort(403);
        }

        if ($transaction->transaction_type !== 'goods_receipt') {
            abort(404, 'Goods Receipt Note PDF is only available for goods receipt transactions.');
        }

        if (! $this->isGlobalInventoryViewer()) {
            $siteId = $transaction->site_id ?: $transaction->destinationWarehouse?->site_id;
            if ($siteId && ! $user->isAssignedToSite((int) $siteId)) {
                abort(403, 'You are not authorized to download GRNs for another site.');
            }
        }

        $relations = ['items.material', 'destinationWarehouse', 'purchaseOrder', 'site', 'performedBy', 'confirmedBy', 'company'];
        if (Schema::hasTable('vendors') && Schema::hasColumn('purchase_orders', 'vendor_id')) {
            $relations[] = 'purchaseOrder.vendor';
        }
        $transaction->load($relations);

        $html = view('inventory.pdf.grn', [
            'transaction' => $transaction,
            'company' => $transaction->company,
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
            'inventory.grn.pdf_exported',
            $transaction,
            null,
            ['transaction_number' => $transaction->transaction_number],
            "Exported printable GRN PDF {$transaction->transaction_number}"
        );

        $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $transaction->transaction_number).'.pdf';

        return response()->streamDownload(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
