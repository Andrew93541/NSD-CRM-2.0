<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    public function workforce(Request $request): View
    {
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));
        $siteId = $request->query('site_id');

        $sites = Site::where('status', 'active')->get();
        $report = $this->attendanceService->getDailyAttendanceAggregation($date, $siteId ? (int)$siteId : null);

        return view('reports.workforce', compact('report', 'sites', 'date', 'siteId'));
    }

    public function workOrders(): View
    {
        $totalOrders = WorkOrder::count();
        $byStatus = WorkOrder::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray();
        $byType = WorkOrder::selectRaw('order_type, count(*) as count')->groupBy('order_type')->pluck('count', 'order_type')->toArray();
        $orders = WorkOrder::with(['site', 'project', 'siteManager', 'seniorManager'])->latest()->get();

        return view('reports.work_orders', compact('totalOrders', 'byStatus', 'byType', 'orders'));
    }

    public function inventory(): View
    {
        $stocks = InventoryStock::with(['material.category', 'warehouse'])->get();
        $totalValuation = $stocks->sum(function ($s) {
            return $s->quantity * ($s->material->standard_unit_cost ?? 0);
        });

        return view('reports.inventory', compact('stocks', 'totalValuation'));
    }
}
