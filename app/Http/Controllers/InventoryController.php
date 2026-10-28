<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function stocks(Request $request): View
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $selectedWarehouseId = $request->query('warehouse_id');

        $query = InventoryStock::with(['material.category', 'warehouse']);

        if ($selectedWarehouseId) {
            $query->where('warehouse_id', $selectedWarehouseId);
        }

        $stocks = $query->paginate(20);

        return view('inventory.stocks', compact('stocks', 'warehouses', 'selectedWarehouseId'));
    }

    public function transactions(Request $request): View
    {
        $query = InventoryTransaction::with(['sourceWarehouse', 'destinationWarehouse', 'site', 'performedBy', 'items.material']);

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->query('type'));
        }

        $transactions = $query->latest('transaction_date')->paginate(20);

        return view('inventory.transactions', compact('transactions'));
    }
}
