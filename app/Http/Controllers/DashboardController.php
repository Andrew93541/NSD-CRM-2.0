<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $companyId = TenantContext::getCompanyId();
        $today = Carbon::today()->format('Y-m-d');
        $user = Auth::user();

        // 1. Workforce & Attendance metrics today
        $todayAttendances = DailyAttendance::where('attendance_date', $today)->get();
        $uniqueWorkersToday = $todayAttendances->pluck('worker_id')->unique()->count();
        $totalHoursToday = round($todayAttendances->sum('worked_hours'), 1);
        $conflictsToday = $todayAttendances->where('has_conflict', true)->count();

        // 2. Sites & Projects
        $activeProjectsCount = Project::where('status', 'active')->count();
        $activeSitesCount = Site::where('status', 'active')->count();

        // 3. Work Order Approvals Pipeline
        $pendingSiteApprovals = WorkOrder::where('status', 'submitted')->count();
        $pendingSeniorApprovals = WorkOrder::where('status', 'site_manager_approved')->count();

        // 4. Procurement Alerts
        $pendingPurchases = PurchaseRequest::where('status', 'pending')->count();
        $emergencyPurchases = PurchaseRequest::where('status', 'pending')->where('is_emergency', true)->count();

        // 5. Low Inventory Items
        $lowStockMaterials = Material::where('materials.is_active', true)
            ->join('inventory_stocks', 'materials.id', '=', 'inventory_stocks.material_id')
            ->whereRaw('inventory_stocks.quantity <= materials.minimum_stock_level')
            ->select('materials.*', 'inventory_stocks.quantity as current_quantity')
            ->take(5)
            ->get();

        // 6. Recent Work Orders
        $recentWorkOrders = WorkOrder::with(['site', 'project'])
            ->latest()
            ->take(5)
            ->get();

        // 7. Role-Specific Queues & Context
        $userRole = $user?->currentRole()?->slug;
        $assignedSites = ($user && $user->hasRole('site_supervisor'))
            ? $user->sites()->where('sites.status', 'active')->get()
            : Site::where('status', 'active')->take(6)->get();

        $pendingTier1Orders = WorkOrder::where('status', 'submitted')->with(['site', 'project'])->latest()->take(5)->get();
        $pendingTier2Orders = WorkOrder::where('status', 'site_manager_approved')->with(['site', 'project'])->latest()->take(5)->get();
        $pendingPurchaseRequestsList = PurchaseRequest::where('status', 'pending')->with(['site', 'warehouse', 'requester', 'items.material'])->latest()->take(5)->get();
        $pendingGoodsReceiptOrders = PurchaseOrder::whereIn('status', ['ordered', 'partially_received'])->with(['site', 'warehouse', 'items.material'])->latest()->take(5)->get();

        return view('dashboard.index', compact(
            'uniqueWorkersToday',
            'totalHoursToday',
            'conflictsToday',
            'activeProjectsCount',
            'activeSitesCount',
            'pendingSiteApprovals',
            'pendingSeniorApprovals',
            'pendingPurchases',
            'emergencyPurchases',
            'lowStockMaterials',
            'recentWorkOrders',
            'today',
            'userRole',
            'assignedSites',
            'pendingTier1Orders',
            'pendingTier2Orders',
            'pendingPurchaseRequestsList',
            'pendingGoodsReceiptOrders'
        ));
    }
}
