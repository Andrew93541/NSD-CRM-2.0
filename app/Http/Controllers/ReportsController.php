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

    protected function hasUnrestrictedReportSiteAccess(\App\Models\User $user, array $roles = ['super_admin', 'company_admin', 'senior_manager', 'office_staff']): bool
    {
        if ($user->hasRole(['super_admin', 'company_admin'])) {
            return true;
        }

        if ($user->isRestrictedToAssignedSites()) {
            return false;
        }

        return $user->hasRole($roles);
    }

    public function workforce(Request $request): View
    {
        Gate::authorize('viewAny', Worker::class);

        $user = Auth::user();
        if ($user->isExplicitlyDenied('reports.view')) {
            abort(403, 'Unauthorized to view operational reports.');
        }

        $hasAllSiteAccess = $this->hasUnrestrictedReportSiteAccess($user);

        $accessibleSiteIds = $hasAllSiteAccess
            ? null
            : $user->accessibleSiteIds();

        $sites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : Site::whereIn('id', $accessibleSiteIds)->where('status', 'active')->orderBy('code')->get();

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
        if ($user->isExplicitlyDenied('reports.view')) {
            abort(403, 'Unauthorized to view operational reports.');
        }

        $hasAllSiteAccess = $this->hasUnrestrictedReportSiteAccess($user);

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
        if ($user->isExplicitlyDenied('reports.view') || $user->isExplicitlyDenied('inventory.view')) {
            abort(403, 'Unauthorized to view inventory reports.');
        }

        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'warehouse_manager', 'office_staff'])
            && !$user->hasPermission('inventory.view')
            && !$user->hasPermission('reports.view')
        ) {
            abort(403, 'Unauthorized to view inventory reports.');
        }

        $hasAllSiteAccess = $this->hasUnrestrictedReportSiteAccess($user, ['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager', 'office_staff']);
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
        if ($user->isExplicitlyDenied('reports.view')) {
            abort(403, 'Unauthorized to view operational reports.');
        }

        $hasAllSiteAccess = $this->hasUnrestrictedReportSiteAccess($user);

        $sites = $hasAllSiteAccess
            ? Site::with('project')->where('status', 'active')->orderBy('code')->get()
            : Site::with('project')->whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->orderBy('code')->get();

        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $projectId = $request->filled('project_id') ? (int) $request->query('project_id') : null;

        if ($projectId) {
            $sites = $sites->where('project_id', $projectId)->values();
        }

        $projects = Project::orderBy('name')->get();
        $siteRows = [];
        $siteIds = $sites->pluck('id')->all();

        $workerCountsBySite = !empty($siteIds)
            ? Worker::where('status', 'active')
                ->whereIn('assigned_site_id', $siteIds)
                ->selectRaw('assigned_site_id, COUNT(*) as aggregate_count')
                ->groupBy('assigned_site_id')
                ->pluck('aggregate_count', 'assigned_site_id')
            : collect();

        $attendancesBySite = !empty($siteIds)
            ? DailyAttendance::whereIn('site_id', $siteIds)
                ->whereDate('attendance_date', '>=', $startDate)
                ->whereDate('attendance_date', '<=', $endDate)
                ->get()
                ->groupBy('site_id')
            : collect();

        $openFlagsBySite = (!empty($siteIds) && Schema::hasTable('attendance_flags'))
            ? AttendanceFlag::whereIn('site_id', $siteIds)
                ->where('status', 'open')
                ->selectRaw('site_id, COUNT(*) as aggregate_count')
                ->groupBy('site_id')
                ->pluck('aggregate_count', 'site_id')
            : collect();

        $openTasksBySite = (!empty($siteIds) && Schema::hasTable('tasks'))
            ? Task::whereIn('site_id', $siteIds)
                ->whereIn('status', ['pending', 'in_progress', 'blocked'])
                ->selectRaw('site_id, COUNT(*) as aggregate_count')
                ->groupBy('site_id')
                ->pluck('aggregate_count', 'site_id')
            : collect();

        $completedTasksBySite = (!empty($siteIds) && Schema::hasTable('tasks'))
            ? Task::whereIn('site_id', $siteIds)
                ->where('status', 'completed')
                ->selectRaw('site_id, COUNT(*) as aggregate_count')
                ->groupBy('site_id')
                ->pluck('aggregate_count', 'site_id')
            : collect();

        foreach ($sites as $site) {
            $atts = $attendancesBySite->get($site->id, collect());

            $siteRows[] = [
                'site' => $site,
                'assigned_workers' => (int) ($workerCountsBySite[$site->id] ?? 0),
                'present_days' => $atts->where('status', 'present')->count(),
                'half_days' => $atts->where('status', 'half_day')->count(),
                'paid_leaves' => $atts->where('status', 'paid_leave')->count(),
                'absent_days' => $atts->where('status', 'absent')->count(),
                'worked_hours' => round((float) $atts->sum('worked_hours'), 2),
                'overtime_hours' => round((float) $atts->sum('overtime_hours'), 2),
                'open_flags' => (int) ($openFlagsBySite[$site->id] ?? 0),
                'open_tasks' => (int) ($openTasksBySite[$site->id] ?? 0),
                'completed_tasks' => (int) ($completedTasksBySite[$site->id] ?? 0),
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
        if (!$user->canPerform('audit_logs.view', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])) {
            abort(403, 'Unauthorized to view operational audit logs.');
        }

        $companyId = \App\Services\Security\TenantContext::getCompanyId();
        $hasAllSiteAccess = $this->hasUnrestrictedReportSiteAccess($user);
        $accessibleSiteIds = $hasAllSiteAccess ? null : $user->accessibleSiteIds();

        $eventFilter = $request->query('event');
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($siteId !== null) {
            $site = Site::findOrFail($siteId);
            if (!$hasAllSiteAccess && !$user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to view audit logs for this site.');
            }
        }

        $sites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : Site::whereIn('id', $accessibleSiteIds)->where('status', 'active')->orderBy('code')->get();

        $logs = collect();
        $distinctEvents = collect();

        if (Schema::hasTable('activity_logs') && $companyId) {
            $distinctEvents = AuditLog::where('company_id', $companyId)
                ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action');

            $logs = AuditLog::with(['actor', 'site', 'project'])
                ->where('company_id', $companyId)
                ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
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
        if ($user->isExplicitlyDenied('audit_logs.export') || $user->isExplicitlyDenied('audit_logs.view')) {
            abort(403, 'Unauthorized to export audit logs.');
        }

        if (!$user->canPerform('audit_logs.export', ['super_admin', 'company_admin', 'senior_manager', 'office_staff'])
            && !$user->hasPermission('audit_logs.view')
        ) {
            abort(403, 'Unauthorized to export audit logs.');
        }

        $companyId = \App\Services\Security\TenantContext::getCompanyId();
        $hasAllSiteAccess = $this->hasUnrestrictedReportSiteAccess($user);
        $accessibleSiteIds = $hasAllSiteAccess ? null : $user->accessibleSiteIds();

        $eventFilter = $request->query('event');
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;

        if ($siteId !== null) {
            $site = Site::findOrFail($siteId);
            if (!$hasAllSiteAccess && !$user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to export audit logs for this site.');
            }
        }

        $logs = (Schema::hasTable('activity_logs') && $companyId)
            ? AuditLog::with(['actor', 'site', 'project'])
                ->where('company_id', $companyId)
                ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
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
                    $log->action ?? $log->event,
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
