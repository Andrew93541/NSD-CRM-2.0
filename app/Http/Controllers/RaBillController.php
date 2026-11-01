<?php

namespace App\Http\Controllers;

use App\Models\BillExport;
use App\Models\MeasurementEntry;
use App\Models\RaBill;
use App\Models\WorkOrder;
use App\Services\Operations\RaBillExportService;
use App\Services\Operations\RaBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RaBillController extends Controller
{
    public function __construct(
        private readonly RaBillingService $raBillingService,
        private readonly RaBillExportService $raBillExportService
    ) {}

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

        $this->raBillingService->recordMeasurement(
            workOrder: $workOrder,
            data: $validated,
            actor: $request->user(),
            photo: $request->file('photo')
        );

        return redirect()->route('work_orders.show', $workOrder)
            ->with('status', __('Site measurement entry recorded successfully.'));
    }

    public function downloadMeasurementPhoto(WorkOrder $workOrder, int $measurementId): StreamedResponse
    {
        Gate::authorize('view', $workOrder);

        $entry = MeasurementEntry::where('work_order_id', $workOrder->id)
            ->findOrFail($measurementId);

        if (!$entry->photo_path || !Storage::disk('local')->exists($entry->photo_path)) {
            abort(404, 'Measurement photo not found in private vault.');
        }

        return Storage::disk('local')->download(
            $entry->photo_path,
            'measurement-' . $entry->id . '-' . basename($entry->photo_path)
        );
    }

    public function create(WorkOrder $workOrder): View
    {
        Gate::authorize('createRaBill', $workOrder);

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

        $wizardItems = [];
        foreach ($workOrder->items as $woItem) {
            $previousQty = $woItem->approvedBilledQuantity();
            $unbilledMeasurements = MeasurementEntry::where('work_order_id', $workOrder->id)
                ->where('work_order_item_id', $woItem->id)
                ->whereNull('ra_bill_id')
                ->whereIn('status', ['verified', 'draft'])
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

        return view('ra_bills.create', compact('workOrder', 'nextRaNumber', 'nextRaLabel', 'wizardItems'));
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('createRaBill', $workOrder);

        $validated = $request->validate([
            'ra_label' => 'nullable|string|max:40',
            'invoice_date' => 'nullable|date',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'cgst_rate' => 'nullable|numeric|min:0|max:28',
            'sgst_rate' => 'nullable|numeric|min:0|max:28',
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
            'cgst_rate' => 'nullable|numeric|min:0|max:28',
            'sgst_rate' => 'nullable|numeric|min:0|max:28',
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

    public function exportPdf(Request $request, WorkOrder $workOrder, RaBill $raBill, string $exportType): StreamedResponse
    {
        Gate::authorize('view', $workOrder);

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $export = $this->raBillExportService->exportPdf($raBill, $exportType, $request->user());

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }

    public function exportXlsx(Request $request, WorkOrder $workOrder, RaBill $raBill): StreamedResponse
    {
        Gate::authorize('view', $workOrder);

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $export = $this->raBillExportService->exportXlsx($raBill, $request->user());

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }

    public function downloadExport(WorkOrder $workOrder, RaBill $raBill, int $exportId): StreamedResponse
    {
        Gate::authorize('view', $workOrder);

        if ((int) $raBill->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $export = BillExport::where('work_order_id', $workOrder->id)
            ->where('ra_bill_id', $raBill->id)
            ->findOrFail($exportId);

        if (!Storage::disk('local')->exists($export->file_path)) {
            abort(404, 'Export file not found in private storage.');
        }

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }
}

