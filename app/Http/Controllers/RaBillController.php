<?php

namespace App\Http\Controllers;

use App\Models\BillExport;
use App\Models\Client;
use App\Models\MeasurementEntry;
use App\Models\RaBill;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Operations\RaBillExportService;
use App\Services\Operations\RaBillingService;
use App\Services\Storage\VaultStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RaBillController extends Controller
{
    public function __construct(
        private readonly RaBillingService $raBillingService,
        private readonly RaBillExportService $raBillExportService
    ) {}

    public function globalIndex(Request $request): View
    {
        $user = $request->user();
        if (! $user || $user->isExplicitlyDenied('ra_bills.view')) {
            abort(403, 'You are not authorized to view RA Bills.');
        }

        if (! $user->canPerform('ra_bills.view', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])
            && ! $user->hasPermission('invoices.view')
        ) {
            abort(403, 'You are not authorized to view RA Bills.');
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites())
            || ($user->hasRole('office_staff') && ! $user->isRestrictedToAssignedSites() && ! $user->sites()->where('sites.company_id', $user->currentCompany()?->id)->exists());

        $accessibleSiteIds = $hasAllSiteAccess ? null : $user->accessibleSiteIds();
        $siteId = $request->filled('site_id') ? (int) $request->input('site_id') : null;
        $clientId = $request->filled('client_id') ? (int) $request->input('client_id') : null;
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', ''));

        if ($siteId !== null && $accessibleSiteIds !== null && ! in_array($siteId, $accessibleSiteIds, true)) {
            abort(403, 'You are not authorized to view RA Bills for another site.');
        }

        $baseQuery = RaBill::with(['workOrder', 'client', 'site', 'creator'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->where(function ($sub) use ($accessibleSiteIds) {
                    $sub->whereNull('site_id')
                        ->orWhereIn('site_id', $accessibleSiteIds);
                });
            })
            ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId))
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('ra_label', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('workOrder', function ($wq) use ($search) {
                            $wq->where('order_number', 'like', "%{$search}%")
                                ->orWhere('po_number', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                });
            });

        $summaryQuery = clone $baseQuery;
        $totalCount = (clone $summaryQuery)->count();
        $approvedNetTotal = (float) (clone $summaryQuery)->whereIn('status', RaBill::APPROVED_STATUSES)->sum('net_payable');
        $pendingCount = (clone $summaryQuery)->whereIn('status', ['draft', 'submitted', 'site_manager_approved'])->count();

        $raBills = $baseQuery->latest('id')->paginate(20)->withQueryString();

        $sites = Site::when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('id', $accessibleSiteIds))
            ->orderBy('name')
            ->get();
        $clients = Schema::hasTable('clients') ? Client::orderBy('name')->get() : collect();

        $createEligibleWorkOrders = WorkOrder::when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
            ->where('is_draft_po', false)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('ra_bills.index', compact(
            'raBills',
            'sites',
            'clients',
            'siteId',
            'clientId',
            'status',
            'search',
            'totalCount',
            'approvedNetTotal',
            'pendingCount',
            'createEligibleWorkOrders'
        ));
    }

    public function createMeasurement(WorkOrder $workOrder): View
    {
        Gate::authorize('recordMeasurement', $workOrder);

        $workOrder->load(['items', 'measurements.workOrderItem', 'measurements.recorder', 'site', 'client']);

        $itemStats = [];
        foreach ($workOrder->items as $item) {
            $billed = $item->approvedBilledQuantity();
            $unbilledVerified = (float) MeasurementEntry::where('work_order_id', $workOrder->id)
                ->where('work_order_item_id', $item->id)
                ->whereNull('ra_bill_id')
                ->whereIn('status', ['verified', 'draft'])
                ->sum('computed_quantity');

            $itemStats[$item->id] = [
                'sr_no' => $item->sr_no,
                'description' => $item->description,
                'unit' => $item->unit,
                'ordered_quantity' => (float) $item->ordered_quantity,
                'billed_quantity' => $billed,
                'unbilled_measured' => round($unbilledVerified, 3),
                'remaining_balance' => max(0.0, round((float) $item->ordered_quantity - $billed, 3)),
            ];
        }

        return view('ra_bills.measurements_create', compact('workOrder', 'itemStats'));
    }

    public function storeMeasurement(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('recordMeasurement', $workOrder);

        $validated = $request->validate([
            'work_order_item_id' => 'required|integer|exists:work_order_items,id',
            'measured_on' => 'nullable|date',
            'location_chainage' => 'nullable|string|max:190',
            'nos' => 'nullable|numeric|min:0.001',
            'length' => 'nullable|numeric|min:0',
            'breadth' => 'nullable|numeric|min:0',
            'depth_height' => 'nullable|numeric|min:0',
            'direct_quantity' => 'nullable|numeric|min:0',
            'hold_quantity' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        try {
            $this->raBillingService->recordMeasurement(
                workOrder: $workOrder,
                data: $validated,
                actor: $request->user(),
                photo: $request->file('photo')
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['direct_quantity' => $e->getMessage()]);
        }

        return redirect()->route('work_orders.show', $workOrder)
            ->with('status', __('Site measurement entry recorded successfully.'));
    }

    public function downloadMeasurementPhoto(WorkOrder $workOrder, int $measurementId): StreamedResponse
    {
        Gate::authorize('view', $workOrder);

        $entry = MeasurementEntry::where('work_order_id', $workOrder->id)
            ->findOrFail($measurementId);

        if (! $entry->photo_path || str_contains($entry->photo_path, '..') || ! VaultStorage::exists($entry->photo_path, $entry->storage_disk ?? null)) {
            abort(404, 'Measurement photo not found in private vault.');
        }

        return VaultStorage::download(
            $entry->photo_path,
            'measurement-'.$entry->id.'-'.preg_replace('/[^A-Za-z0-9._\- ]/', '_', basename($entry->photo_path)),
            ['X-Content-Type-Options' => 'nosniff'],
            $entry->storage_disk ?? null
        );
    }

    public function verifyMeasurement(Request $request, WorkOrder $workOrder, int $measurementId): RedirectResponse
    {
        Gate::authorize('view', $workOrder);

        $user = $request->user();
        if (! $user || $user->isExplicitlyDenied('measurements.approve') || ! $user->canPerform('measurements.approve', ['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'You are not authorized to verify site measurements.');
        }

        $entry = MeasurementEntry::where('work_order_id', $workOrder->id)->findOrFail($measurementId);

        if ($entry->recorded_by && (int) $entry->recorded_by === (int) $user->id) {
            abort(403, 'You cannot verify your own draft measurement entry.');
        }

        try {
            $this->raBillingService->verifyMeasurement($entry, $user);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Site measurement verified and unlocked for RA Billing.'));
    }

    public function updateMeasurement(Request $request, WorkOrder $workOrder, int $measurementId): RedirectResponse
    {
        Gate::authorize('recordMeasurement', $workOrder);

        $entry = MeasurementEntry::where('work_order_id', $workOrder->id)->findOrFail($measurementId);

        $validated = $request->validate([
            'measured_on' => 'nullable|date',
            'location_chainage' => 'nullable|string|max:190',
            'nos' => 'nullable|numeric|min:0.001',
            'length' => 'nullable|numeric|min:0',
            'breadth' => 'nullable|numeric|min:0',
            'depth_height' => 'nullable|numeric|min:0',
            'direct_quantity' => 'nullable|numeric|min:0',
            'hold_quantity' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $this->raBillingService->updateMeasurement($entry, $validated, $request->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Site measurement entry updated.'));
    }

    public function destroyMeasurement(Request $request, WorkOrder $workOrder, int $measurementId): RedirectResponse
    {
        Gate::authorize('recordMeasurement', $workOrder);

        $entry = MeasurementEntry::where('work_order_id', $workOrder->id)->findOrFail($measurementId);

        try {
            $this->raBillingService->deleteMeasurement($entry, $request->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Unbilled site measurement entry deleted.'));
    }

    public function exportMeasurementBookPdf(Request $request, WorkOrder $workOrder): StreamedResponse
    {
        Gate::authorize('view', $workOrder);
        $this->authorizeBillExport($request->user(), 'ra_bill');

        $rendered = $this->raBillExportService->renderMeasurementBookPdf($workOrder, $request->user());

        return response()->streamDownload(function () use ($rendered) {
            echo $rendered['binary'];
        }, $rendered['file_name'], [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function create(WorkOrder $workOrder): View|RedirectResponse
    {
        Gate::authorize('createRaBill', $workOrder);

        if ($workOrder->isDraftPo()) {
            return redirect()->route('work_orders.show', $workOrder)->withErrors([
                'work_order' => 'Billing is blocked while the Work Order is in Draft PO status. Upload or confirm the final PO revision before raising an RA bill.',
            ]);
        }

        $workOrder->load(['items', 'company', 'client', 'site', 'raBills']);

        $lastRaNumber = (int) RaBill::where('work_order_id', $workOrder->id)
            ->where('status', '!=', 'rejected')
            ->max('ra_number');
        $nextRaNumber = $lastRaNumber + 1;
        $nextRaLabel = RaBill::formatOrdinalLabel($nextRaNumber);

        $prevApprovedBill = RaBill::where('work_order_id', $workOrder->id)
            ->whereIn('status', RaBill::APPROVED_STATUSES)
            ->orderByDesc('ra_number')
            ->with('items')
            ->first();

        $prevExcessByItem = [];
        if ($prevApprovedBill) {
            foreach ($prevApprovedBill->items as $prevItem) {
                $prevExcessByItem[$prevItem->work_order_item_id] = (float) $prevItem->excess_quantity;
            }
        }

        $unbilledDraftCount = MeasurementEntry::where('work_order_id', $workOrder->id)
            ->whereNull('ra_bill_id')
            ->where('status', 'draft')
            ->count();

        $wizardItems = [];
        foreach ($workOrder->items as $woItem) {
            $previousQty = $woItem->approvedBilledQuantity();
            $unbilledMeasurements = MeasurementEntry::where('work_order_id', $workOrder->id)
                ->where('work_order_item_id', $woItem->id)
                ->whereNull('ra_bill_id')
                ->where('status', 'verified')
                ->get();

            $measuredQty = (float) $unbilledMeasurements->sum('computed_quantity');
            $measuredHold = (float) $unbilledMeasurements->sum('hold_quantity');
            $carriedExcess = $prevExcessByItem[$woItem->id] ?? 0.0;

            $wizardItems[] = [
                'id' => $woItem->id,
                'sr_no' => (string) $woItem->sr_no,
                'service_code' => $woItem->service_code ?? '',
                'sac_code' => $woItem->sac_code ?? '995419',
                'description' => $woItem->description,
                'unit' => $woItem->unit,
                'ordered_quantity' => (float) $woItem->ordered_quantity,
                'rate' => (float) $woItem->rate,
                'previous_quantity' => $previousQty,
                'carried_excess' => round($carriedExcess, 3),
                'measured_quantity' => round($measuredQty, 3),
                'claimed_quantity' => round($carriedExcess + $measuredQty, 3),
                'hold_quantity' => round($measuredHold, 3),
            ];
        }

        return view('ra_bills.create', compact('workOrder', 'nextRaNumber', 'nextRaLabel', 'wizardItems', 'unbilledDraftCount'));
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('createRaBill', $workOrder);

        if ($workOrder->isDraftPo()) {
            return back()->withErrors([
                'work_order' => 'Billing is blocked while the Work Order is in Draft PO status. Upload or confirm the final PO revision before raising an RA bill.',
            ]);
        }

        $validated = $request->validate([
            'ra_label' => 'nullable|string|max:40',
            'invoice_date' => 'nullable|date',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'tax_mode' => 'nullable|in:cgst_sgst,igst',
            'cgst_rate' => 'nullable|numeric|min:0|max:28',
            'sgst_rate' => 'nullable|numeric|min:0|max:28',
            'igst_rate' => 'nullable|numeric|min:0|max:28',
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_deduction' => 'nullable|numeric|min:0',
            'advance_recovery' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'rounding_mode' => 'nullable|in:paisa,rupee',
            'items' => 'nullable|array',
            'items.*.claimed_quantity' => 'nullable|numeric|min:0',
            'items.*.hold_quantity' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:500',
        ]);

        try {
            $raBill = $this->raBillingService->createOrRecalculateRaBill(
                workOrder: $workOrder,
                data: $validated,
                actor: $request->user()
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('work_orders.ra_bills.show', [$workOrder, $raBill])
            ->with('status', __('RA Bill :label generated with Work Order Quantity Cap enforcement.', ['label' => $raBill->ra_label]));
    }

    public function show(WorkOrder $workOrder, RaBill $raBill): View
    {
        Gate::authorize('view', $workOrder);

        $user = request()->user();
        if ($user && $user->isExplicitlyDenied('ra_bills.view')) {
            abort(403, 'You are not authorized to view RA Bills.');
        }

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $raBill->load(['items', 'measurements.recorder', 'exports.exporter', 'creator', 'siteManager', 'seniorManager', 'client', 'site']);

        return view('ra_bills.show', compact('workOrder', 'raBill'));
    }

    public function update(Request $request, WorkOrder $workOrder, RaBill $raBill): RedirectResponse
    {
        Gate::authorize('createRaBill', $workOrder);

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $validated = $request->validate([
            'ra_label' => 'nullable|string|max:40',
            'invoice_date' => 'nullable|date',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'tax_mode' => 'nullable|in:cgst_sgst,igst',
            'cgst_rate' => 'nullable|numeric|min:0|max:28',
            'sgst_rate' => 'nullable|numeric|min:0|max:28',
            'igst_rate' => 'nullable|numeric|min:0|max:28',
            'retention_percent' => 'nullable|numeric|min:0|max:100',
            'retention_deduction' => 'nullable|numeric|min:0',
            'advance_recovery' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'rounding_mode' => 'nullable|in:paisa,rupee',
            'items' => 'nullable|array',
            'items.*.claimed_quantity' => 'nullable|numeric|min:0',
            'items.*.hold_quantity' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:500',
        ]);

        try {
            $this->raBillingService->createOrRecalculateRaBill(
                workOrder: $workOrder,
                data: $validated,
                actor: $request->user(),
                existingBill: $raBill
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('work_orders.ra_bills.show', [$workOrder, $raBill])
            ->with('status', __('RA Bill recalculated successfully.'));
    }

    public function submit(Request $request, WorkOrder $workOrder, RaBill $raBill): RedirectResponse
    {
        Gate::authorize('createRaBill', $workOrder);

        try {
            $this->raBillingService->submitRaBill($raBill, $request->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('RA Bill submitted for Site Manager verification.'));
    }

    public function approveSite(Request $request, WorkOrder $workOrder, RaBill $raBill): RedirectResponse
    {
        Gate::authorize('approveRaBillSite', [$workOrder, $raBill]);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->raBillingService->approveSiteManager($raBill, $request->user(), $validated['notes'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('RA Bill verified by Site Manager (Tier 1). Awaiting Senior Manager approval.'));
    }

    public function approveSenior(Request $request, WorkOrder $workOrder, RaBill $raBill): RedirectResponse
    {
        Gate::authorize('approveRaBillSenior', [$workOrder, $raBill]);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->raBillingService->approveSeniorManager($raBill, $request->user(), $validated['notes'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('RA Bill finally approved by Senior Manager (Tier 2). Quantities locked.'));
    }

    public function reject(Request $request, WorkOrder $workOrder, RaBill $raBill): RedirectResponse
    {
        Gate::authorize('rejectRaBill', [$workOrder, $raBill]);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $this->raBillingService->rejectRaBill($raBill, $request->user(), $validated['rejection_reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('RA Bill rejected and measurement entries released.'));
    }

    protected function authorizeBillExport(User $user, string $exportType = 'ra_bill'): void
    {
        if ($exportType === 'tax_invoice') {
            if ($user->isExplicitlyDenied('invoices.export') || $user->isExplicitlyDenied('ra_bills.export')) {
                abort(403, 'You are not authorized to export Tax Invoices.');
            }
            if (! $user->canPerform('invoices.export', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])
                && ! $user->hasPermission('ra_bills.export')
            ) {
                abort(403, 'You are not authorized to export Tax Invoices.');
            }

            return;
        }

        if ($user->isExplicitlyDenied('ra_bills.export')) {
            abort(403, 'You are not authorized to export RA Bills.');
        }

        if (! $user->canPerform('ra_bills.export', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])) {
            abort(403, 'You are not authorized to export RA Bills.');
        }
    }

    public function exportPdf(Request $request, WorkOrder $workOrder, RaBill $raBill, string $exportType): StreamedResponse
    {
        Gate::authorize('view', $workOrder);
        $this->authorizeBillExport($request->user(), $exportType);

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $export = $this->raBillExportService->exportPdf($raBill, $exportType, $request->user());

        return VaultStorage::download($export->file_path, $export->file_name, [], $export->storage_disk ?? null);
    }

    public function exportXlsx(Request $request, WorkOrder $workOrder, RaBill $raBill): StreamedResponse
    {
        Gate::authorize('view', $workOrder);
        $this->authorizeBillExport($request->user(), 'xlsx');

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $export = $this->raBillExportService->exportXlsx($raBill, $request->user());

        return VaultStorage::download($export->file_path, $export->file_name, [], $export->storage_disk ?? null);
    }

    public function downloadExport(WorkOrder $workOrder, RaBill $raBill, int $exportId): StreamedResponse
    {
        Gate::authorize('view', $workOrder);
        $this->authorizeBillExport(request()->user(), 'download');

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $export = BillExport::where('work_order_id', $workOrder->id)
            ->where('ra_bill_id', $raBill->id)
            ->findOrFail($exportId);

        if (! VaultStorage::exists($export->file_path, $export->storage_disk ?? null)) {
            abort(404, 'Export file not found in private storage.');
        }

        return VaultStorage::download($export->file_path, $export->file_name, [], $export->storage_disk ?? null);
    }
}
