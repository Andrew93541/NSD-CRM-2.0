<?php

namespace App\Http\Controllers;

use App\Models\AttendanceFlag;
use App\Models\AuditLog;
use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\Project;
use App\Models\Site;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    public function workforce(Request $request): View
    {
        Gate::authorize('viewAny', Worker::class);

        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']);

        $sites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : $user->sites()->where('status', 'active')->orderBy('code')->get();

        $accessibleSiteIds = $hasAllSiteAccess
            ? null
            : $user->accessibleSiteIds();

        $mode = $request->query('mode', 'date');
        if (!in_array($mode, ['date', 'month'], true)) {
            $mode = 'date';
        }

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));
        $month = $request->query('month', Carbon::parse($date)->format('Y-m'));
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $workerType = $request->query('worker_type');
        $presenceFilter = $request->query('presence_filter', 'all');
        $search = $request->query('search');

        if ($siteId) {
            $site = Site::findOrFail($siteId);
            if (!$user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to view attendance report for this site.');
            }
        }

        $report = $this->attendanceService->getDailyAttendanceAggregation($date, $siteId);
        $dateRegister = null;
        $monthMatrix = null;

        if ($mode === 'month') {
            $monthMatrix = $this->attendanceService->getMonthWiseAttendanceMatrix(
                $month,
                $siteId,
                $workerType,
                $search,
                $accessibleSiteIds
            );
        } else {
            $dateRegister = $this->attendanceService->getDateWiseAttendanceRegister(
                $date,
                $siteId,
                $workerType,
                $presenceFilter,
                $search,
                $accessibleSiteIds
            );
        }

        return view('reports.workforce', compact(
            'report',
            'sites',
            'mode',
            'date',
            'month',
            'siteId',
            'workerType',
            'presenceFilter',
            'search',
            'dateRegister',
            'monthMatrix'
        ));
    }

    public function workOrders(): View
    {
        Gate::authorize('viewAny', WorkOrder::class);

        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']);

        $baseQuery = WorkOrder::query();
        if (!$hasAllSiteAccess) {
            $accessibleSiteIds = $user->accessibleSiteIds();
            $baseQuery->where(function ($q) use ($accessibleSiteIds) {
                $q->whereNull('site_id')
                  ->orWhereIn('site_id', $accessibleSiteIds);
            });
        }

        $totalOrders = (clone $baseQuery)->count();
        $byStatus = (clone $baseQuery)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray();
        $byType = (clone $baseQuery)->selectRaw('order_type, count(*) as count')->groupBy('order_type')->pluck('count', 'order_type')->toArray();
        $orders = (clone $baseQuery)->with(['site', 'project', 'siteManager', 'seniorManager'])->latest()->get();

        return view('reports.work_orders', compact('totalOrders', 'byStatus', 'byType', 'orders'));
    }

    public function inventory(): View
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'warehouse_manager', 'office_staff'])
            && !$user->hasPermission('inventory.view')
            && !$user->hasPermission('reports.view')
        ) {
            abort(403, 'Unauthorized to view inventory reports.');
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager', 'office_staff']);
        $accessibleSiteIds = $hasAllSiteAccess ? null : $user->accessibleSiteIds();

        $stocks = InventoryStock::with(['material.category', 'warehouse'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereHas('warehouse', function ($wq) use ($accessibleSiteIds) {
                    $wq->whereNull('site_id')
                       ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->get();

        $totalValuation = $stocks->sum(function ($s) {
            return $s->quantity * ($s->material->standard_unit_cost ?? 0);
        });

        return view('reports.inventory', compact('stocks', 'totalValuation'));
    }

    /**
     * Multi-Site Executive Comparison Report (Attendance, Man-Hours, Overtime, Tasks & Anomaly Flags).
     */
    public function multiSiteComparison(Request $request): View
    {
        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']);

        $sites = $hasAllSiteAccess
            ? Site::with('project')->where('status', 'active')->orderBy('code')->get()
            : $user->sites()->with('project')->where('status', 'active')->orderBy('code')->get();

        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $projectId = $request->filled('project_id') ? (int) $request->query('project_id') : null;

        if ($projectId) {
            $sites = $sites->where('project_id', $projectId)->values();
        }

        $projects = Project::orderBy('name')->get();
        $siteRows = [];

        foreach ($sites as $site) {
            $assignedWorkersCount = Worker::where('status', 'active')
                ->where('assigned_site_id', $site->id)
                ->count();

            $atts = DailyAttendance::where('site_id', $site->id)
                ->whereDate('attendance_date', '>=', $startDate)
                ->whereDate('attendance_date', '<=', $endDate)
                ->get();

            $presentDays = $atts->where('status', 'present')->count();
            $halfDays = $atts->where('status', 'half_day')->count();
            $paidLeaves = $atts->where('status', 'paid_leave')->count();
            $absentDays = $atts->where('status', 'absent')->count();
            $workedHours = round((float) $atts->sum('worked_hours'), 2);
            $overtimeHours = round((float) $atts->sum('overtime_hours'), 2);

            $openFlagsCount = Schema::hasTable('attendance_flags')
                ? AttendanceFlag::where('site_id', $site->id)->where('status', 'open')->count()
                : 0;

            $openTasksCount = Schema::hasTable('tasks')
                ? Task::where('site_id', $site->id)->whereIn('status', ['pending', 'in_progress', 'blocked'])->count()
                : 0;

            $completedTasksCount = Schema::hasTable('tasks')
                ? Task::where('site_id', $site->id)->where('status', 'completed')->count()
                : 0;

            $siteRows[] = [
                'site' => $site,
                'assigned_workers' => $assignedWorkersCount,
                'present_days' => $presentDays,
                'half_days' => $halfDays,
                'paid_leaves' => $paidLeaves,
                'absent_days' => $absentDays,
                'worked_hours' => $workedHours,
                'overtime_hours' => $overtimeHours,
                'open_flags' => $openFlagsCount,
                'open_tasks' => $openTasksCount,
                'completed_tasks' => $completedTasksCount,
            ];
        }

        return view('reports.multi_site', compact(
            'siteRows',
            'projects',
            'projectId',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Append-Only Security & Operational Audit Log Viewer + CSV Export.
     */
    public function auditLogs(Request $request): View
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])) {
            abort(403, 'Unauthorized to view operational audit logs.');
        }

        $eventFilter = $request->query('event');
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $sites = Site::where('status', 'active')->orderBy('code')->get();

        $logs = collect();
        $distinctEvents = collect();

        if (Schema::hasTable('activity_logs')) {
            $distinctEvents = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');

            $logs = AuditLog::with(['actor', 'site', 'project'])
                ->when($eventFilter, fn ($q) => $q->where('action', $eventFilter))
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
                ->latest('created_at')
                ->latest('id')
                ->limit(250)
                ->get();
        }

        return view('reports.audit_logs', compact(
            'logs',
            'distinctEvents',
            'sites',
            'eventFilter',
            'siteId',
            'startDate',
            'endDate'
        ));
    }

    public function exportAuditLogsCsv(Request $request): StreamedResponse
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
            abort(403, 'Unauthorized to export audit logs.');
        }

        $eventFilter = $request->query('event');
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;

        $logs = Schema::hasTable('activity_logs')
            ? AuditLog::with(['actor', 'site', 'project'])
                ->when($eventFilter, fn ($q) => $q->where('action', $eventFilter))
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->latest('created_at')
                ->limit(1000)
                ->get()
            : collect();

        $filename = 'nsd_audit_logs_' . Carbon::now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Timestamp',
                'Event',
                'Actor',
                'Site Code',
                'Subject Type',
                'Subject ID',
                'IP Address',
                'New Values JSON',
            ]);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->event,
                    $log->actor?->name ?? 'System',
                    $log->site?->code ?? '-',
                    class_basename($log->subject_type ?? ''),
                    $log->subject_id ?? '-',
                    $log->ip_address ?? '-',
                    $log->new_values ? json_encode($log->new_values) : '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
