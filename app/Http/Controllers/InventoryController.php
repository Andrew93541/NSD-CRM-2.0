<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    protected function isGlobalInventoryViewer(): bool
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
            ->get();

        $selectedWarehouseId = $request->query('warehouse_id');

        if ($selectedWarehouseId) {
            $selectedWarehouse = Warehouse::findOrFail((int) $selectedWarehouseId);
            if ($accessibleSiteIds !== null && $selectedWarehouse->site_id && !in_array((int) $selectedWarehouse->site_id, $accessibleSiteIds, true)) {
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

        $stocks = $query->paginate(20);

        return view('inventory.stocks', compact('stocks', 'warehouses', 'selectedWarehouseId'));
    }

    public function transactions(Request $request): View
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $user = Auth::user();
        $accessibleSiteIds = $this->isGlobalInventoryViewer() ? null : $user->accessibleSiteIds();

        $query = InventoryTransaction::with(['sourceWarehouse', 'destinationWarehouse', 'site', 'performedBy', 'items.material'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            });

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->query('type'));
        }

        $transactions = $query->latest('transaction_date')->paginate(20);

        return view('inventory.transactions', compact('transactions'));
    }
}
