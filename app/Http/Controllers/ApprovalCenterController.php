<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\LeaveRequest;
use App\Models\MeasurementEntry;
use App\Models\PurchaseRequest;
use App\Models\RaBill;
use App\Models\WorkOrder;
use App\Models\WorkOrderRevision;
use App\Services\Security\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ApprovalCenterController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $companyId = (int) TenantContext::getCompanyId();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites());

        $accessibleSiteIds = $hasAllSiteAccess ? null : $user->accessibleSiteIds();
        $gate = Gate::forUser($user);
        $category = trim((string) $request->query('category', 'all'));

        // 1. Work Orders & Revisions
        $pendingWorkOrders = WorkOrder::with(['site', 'client', 'creator'])
            ->whereIn('status', ['submitted', 'site_manager_approved'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (WorkOrder $wo) => $gate->allows('approveSite', $wo) || $gate->allows('approveSenior', $wo))
            ->values();

        $pendingRevisions = Schema::hasTable('work_order_revisions')
            ? WorkOrderRevision::with(['workOrder.site', 'changer'])
                ->where('status', 'pending_approval')
                ->whereHas('workOrder', function ($q) use ($companyId, $accessibleSiteIds) {
                    $q->where('company_id', $companyId)
                        ->when($accessibleSiteIds !== null, function ($sub) use ($accessibleSiteIds) {
                            $sub->where(function ($s) use ($accessibleSiteIds) {
                                $s->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                            });
                        });
                })
                ->latest('id')
                ->limit(200)
                ->get()
                ->filter(fn (WorkOrderRevision $rev) => $rev->workOrder && $gate->allows('approveRevision', [$rev->workOrder, $rev]))
                ->values()
            : collect();

        // 2. RA Bills
        $pendingRaBills = RaBill::with(['workOrder', 'site', 'client', 'creator'])
            ->whereIn('status', ['draft', 'submitted', 'site_manager_approved'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(function (RaBill $bill) use ($gate) {
                $wo = $bill->workOrder;
                if (! $wo) {
                    return false;
                }

                return $gate->allows('approveRaBillSite', [$wo, $bill])
                    || $gate->allows('approveRaBillSenior', [$wo, $bill]);
            })
            ->values();

        // 3. Purchase Requests
        $pendingPurchaseRequests = PurchaseRequest::with(['site', 'warehouse', 'requester', 'items.material'])
            ->where('status', 'pending')
            ->where('requested_by', '!=', $user->id)
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (PurchaseRequest $pr) => $gate->allows('approve', $pr))
            ->values();

        // 4. Stock Adjustments
        $pendingAdjustments = InventoryTransaction::with(['sourceWarehouse', 'destinationWarehouse', 'site', 'performedBy', 'items.material'])
            ->where('transaction_type', 'stock_adjustment')
            ->where('status', 'pending_confirmation')
            ->where(function ($q) use ($user) {
                $q->whereNull('performed_by')->orWhere('performed_by', '!=', $user->id);
            })
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (InventoryTransaction $tx) => $gate->allows('approveAdjustment', [InventoryStock::class, $tx]))
            ->values();

        // 5. Draft Site Measurements
        $canVerifyMeasurements = ! $user->isExplicitlyDenied('measurements.approve')
            && $user->canPerform('measurements.approve', ['super_admin', 'company_admin', 'senior_manager', 'site_manager']);

        $pendingMeasurements = $canVerifyMeasurements
            ? MeasurementEntry::with(['workOrder.site', 'workOrderItem', 'recorder'])
                ->where('status', 'draft')
                ->whereNull('ra_bill_id')
                ->where(function ($q) use ($user) {
                    $q->whereNull('recorded_by')->orWhere('recorded_by', '!=', $user->id);
                })
                ->whereHas('workOrder', function ($wq) use ($companyId, $accessibleSiteIds) {
                    $wq->where('company_id', $companyId)
                        ->when($accessibleSiteIds !== null, function ($sub) use ($accessibleSiteIds) {
                            $sub->where(function ($s) use ($accessibleSiteIds) {
                                $s->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                            });
                        });
                })
                ->latest('id')
                ->limit(200)
                ->get()
                ->filter(fn (MeasurementEntry $entry) => $entry->workOrder && $gate->allows('view', $entry->workOrder))
                ->values()
            : collect();

        // 6. Attendance Corrections & Leave Requests
        $canApproveAttendance = $user->hasPermission('attendance.approve');

        $pendingCorrections = $canApproveAttendance
            ? AttendanceCorrection::with(['attendance.worker', 'attendance.site', 'requester'])
                ->where('status', 'pending')
                ->where('requested_by', '!=', $user->id)
                ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                    $q->whereHas('attendance', fn ($aq) => $aq->whereIn('site_id', $accessibleSiteIds));
                })
                ->latest('id')
                ->limit(200)
                ->get()
            : collect();

        $pendingLeaves = ($canApproveAttendance && Schema::hasTable('leave_requests'))
            ? LeaveRequest::with(['worker', 'site', 'requester', 'leaveType'])
                ->where('status', 'pending')
                ->where(function ($q) use ($user) {
                    $q->whereNull('requested_by')->orWhere('requested_by', '!=', $user->id);
                })
                ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                    $q->where(function ($sub) use ($accessibleSiteIds) {
                        $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds);
                    });
                })
                ->latest('id')
                ->limit(200)
                ->get()
            : collect();

        $counts = [
            'work_orders' => $pendingWorkOrders->count() + $pendingRevisions->count(),
            'ra_bills' => $pendingRaBills->count(),
            'procurement' => $pendingPurchaseRequests->count(),
            'inventory' => $pendingAdjustments->count(),
            'measurements' => $pendingMeasurements->count(),
            'attendance' => $pendingCorrections->count() + $pendingLeaves->count(),
        ];
        $counts['all'] = array_sum($counts);

        return view('approvals.index', compact(
            'category',
            'counts',
            'pendingWorkOrders',
            'pendingRevisions',
            'pendingRaBills',
            'pendingPurchaseRequests',
            'pendingAdjustments',
            'pendingMeasurements',
            'pendingCorrections',
            'pendingLeaves'
        ));
    }
}
