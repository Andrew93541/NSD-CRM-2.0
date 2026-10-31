<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceFlag;
use App\Models\AuditLog;
use App\Models\CrmNotification;
use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\LeaveRequest;
use App\Models\Material;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\WorkerDocument;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $companyId = TenantContext::getCompanyId();
        $today = Carbon::today()->format('Y-m-d');
        $user = Auth::user();

        $isSupervisorOnly = $user->hasRole(['site_supervisor', 'supervisor'])
            && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff', 'warehouse_manager']);

        $isSiteManagerOnly = $user->hasRole('site_manager')
            && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']);

        $scopedSiteIds = ($isSupervisorOnly || $isSiteManagerOnly)
            ? $user->sites()->pluck('sites.id')->all()
            : null;

        // 1. Workforce & Attendance metrics today
        $todayAttendances = DailyAttendance::where('attendance_date', $today)
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->get();
        $uniqueWorkersToday = $todayAttendances->pluck('worker_id')->unique()->count();
        $totalHoursToday = round($todayAttendances->sum('worked_hours'), 1);
        $conflictsToday = $todayAttendances->where('has_conflict', true)->count();

        // 2. Sites & Projects
        $activeProjectsCount = Project::where('status', 'active')->count();
        $activeSitesCount = Site::where('status', 'active')
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('id', $scopedSiteIds))
            ->count();

        // 3. Work Order Approvals Pipeline
        $pendingSiteApprovals = WorkOrder::where('status', 'submitted')
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->count();
        $pendingSeniorApprovals = WorkOrder::where('status', 'site_manager_approved')->count();

        // 4. Procurement Alerts
        $pendingPurchases = PurchaseRequest::where('status', 'pending')
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->count();
        $emergencyPurchases = PurchaseRequest::where('status', 'pending')
            ->where('is_emergency', true)
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->count();

        // 5. Low Inventory Items
        $lowStockMaterials = Material::where('materials.is_active', true)
            ->join('inventory_stocks', 'materials.id', '=', 'inventory_stocks.material_id')
            ->whereRaw('inventory_stocks.quantity <= materials.minimum_stock_level')
            ->select('materials.*', 'inventory_stocks.quantity as current_quantity')
            ->take(5)
            ->get();

        // 6. Recent Work Orders
        $recentWorkOrders = WorkOrder::with(['site', 'project'])
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->latest()
            ->take(5)
            ->get();

        // 7. Role-Specific Queues & Context
        $userRole = $user?->currentRole()?->slug;
        $assignedSites = ($scopedSiteIds !== null)
            ? $user->sites()->where('sites.status', 'active')->get()
            : Site::where('status', 'active')->take(6)->get();

        $pendingTier1Orders = WorkOrder::where('status', 'submitted')
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->with(['site', 'project'])
            ->latest()
            ->take(5)
            ->get();
        $pendingTier2Orders = WorkOrder::where('status', 'site_manager_approved')
            ->with(['site', 'project'])
            ->latest()
            ->take(5)
            ->get();
        $pendingPurchaseRequestsList = PurchaseRequest::where('status', 'pending')
            ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
            ->with(['site', 'warehouse', 'requester', 'items.material'])
            ->latest()
            ->take(5)
            ->get();
        $pendingGoodsReceiptOrders = PurchaseOrder::whereIn('status', ['ordered', 'partially_received'])
            ->with(['site', 'warehouse', 'items.material'])
            ->latest()
            ->take(5)
            ->get();

        // 8. Phase 5: Overdue Tasks, Expiring Worker Docs, Open Flags, Pending Leaves, & Recent Audit Logs
        $overdueTasksCount = Schema::hasTable('tasks')
            ? Task::whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
                ->count()
            : 0;

        $activeTasksCount = Schema::hasTable('tasks')
            ? Task::whereIn('status', ['pending', 'in_progress', 'blocked'])
                ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
                ->count()
            : 0;

        $openAttendanceFlagsCount = Schema::hasTable('attendance_flags')
            ? AttendanceFlag::where('status', 'open')
                ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
                ->count()
            : 0;

        $pendingAttendanceCorrectionsCount = AttendanceCorrection::where('status', 'pending')
            ->when($scopedSiteIds !== null, function ($q) use ($scopedSiteIds) {
                $q->whereHas('dailyAttendance', fn ($aq) => $aq->whereIn('site_id', $scopedSiteIds));
            })
            ->count();

        $pendingLeavesCount = Schema::hasTable('leave_requests')
            ? LeaveRequest::where('status', 'pending')
                ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
                ->count()
            : 0;

        $expiringDocumentsCount = (Schema::hasTable('worker_documents') && Schema::hasColumn('worker_documents', 'expiry_date'))
            ? WorkerDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', Carbon::today()->addDays(30)->format('Y-m-d'))
                ->count()
            : 0;

        $recentActivityLogs = Schema::hasTable('activity_logs')
            ? AuditLog::with(['actor', 'site'])
                ->when($scopedSiteIds !== null, fn ($q) => $q->whereIn('site_id', $scopedSiteIds))
                ->latest('created_at')
                ->take(6)
                ->get()
            : collect();

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
            'pendingGoodsReceiptOrders',
            'overdueTasksCount',
            'activeTasksCount',
            'openAttendanceFlagsCount',
            'pendingAttendanceCorrectionsCount',
            'pendingLeavesCount',
            'expiringDocumentsCount',
            'recentActivityLogs'
        ));
    }
}
