<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\MeasurementEntry;
use App\Models\RaBill;
use App\Models\RaBillItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RaBillingService
{

    /**
     * Pure deterministic calculation of a single RA Bill line item with Work Order Quantity Cap
     * and separate hold_quantity.
     *
     * Rules:
     * - effective_claimed = max(0, claimed - hold)
     * - remaining_available = max(0, ordered - previous)
     * - billable = max(0, min(effective_claimed, remaining_available))
     * - excess = max(0, effective_claimed - billable)
     * - cumulative = previous + billable (never exceeds ordered)
     * - balance = max(0, ordered - cumulative)
     */
    public static function computeLineItemBill(
        float $ordered,
        float $previous,
        float $claimed,
        float $hold,
        float $rate
    ): array {
        $ordered = max(0.0, round($ordered, 3));
        $previous = max(0.0, round($previous, 3));
        $claimed = max(0.0, round($claimed, 3));
        $hold = max(0.0, round($hold, 3));
        $rate = max(0.0, round($rate, 2));

        $effectiveClaimed = max(0.0, round($claimed - $hold, 3));
        $remainingAvailable = max(0.0, round($ordered - $previous, 3));
        $currentBillable = max(0.0, min($effectiveClaimed, $remainingAvailable));
        $excess = max(0.0, round($effectiveClaimed - $currentBillable, 3));
        $cumulative = round($previous + $currentBillable, 3);
        $balance = max(0.0, round($ordered - $cumulative, 3));

        $consumptionPercent = $ordered > 0
            ? round(($cumulative / $ordered) * 100, 2)
            : 0.00;

        if ($consumptionPercent >= 100.0 || $excess > 0.0) {
            $warningLevel = 'cap_100';
        } elseif ($consumptionPercent >= 90.0) {
            $warningLevel = 'warning_90';
        } elseif ($consumptionPercent >= 80.0) {
            $warningLevel = 'warning_80';
        } else {
            $warningLevel = 'normal';
        }

        return [
            'ordered_quantity' => $ordered,
            'rate' => $rate,
            'order_amount' => round($ordered * $rate, 2),
            'previous_quantity' => $previous,
            'claimed_quantity' => $claimed,
            'hold_quantity' => $hold,
            'effective_claimed_quantity' => $effectiveClaimed,
            'remaining_available_quantity' => $remainingAvailable,
            'current_billable_quantity' => $currentBillable,
            'excess_quantity' => $excess,
            'cumulative_quantity' => $cumulative,
            'balance_quantity' => $balance,
            'consumption_percent' => $consumptionPercent,
            'warning_level' => $warningLevel,
            'previous_amount' => round($previous * $rate, 2),
            'current_amount' => round($currentBillable * $rate, 2),
            'cumulative_amount' => round($cumulative * $rate, 2),
            'excess_amount' => round($excess * $rate, 2),
        ];
    }

    /**
     * Record a site measurement sheet entry against a WorkOrderItem.
     */
    public function recordMeasurement(
        WorkOrder $workOrder,
        array $data,
        User $actor,
        ?UploadedFile $photo = null
    ): MeasurementEntry {
        return DB::transaction(function () use ($workOrder, $data, $actor, $photo) {
            /** @var WorkOrderItem $woItem */
            $woItem = WorkOrderItem::where('work_order_id', $workOrder->id)
                ->findOrFail($data['work_order_item_id']);

            $photoPath = null;
            if ($photo) {
                $photoPath = $photo->store(
                    "companies/{$workOrder->company_id}/work_orders/{$workOrder->id}/measurements",
                    'local'
                );
            }

            $entry = MeasurementEntry::create([
                'company_id' => $workOrder->company_id,
                'work_order_id' => $workOrder->id,
                'work_order_item_id' => $woItem->id,
                'site_id' => $data['site_id'] ?? $workOrder->site_id,
                'measured_on' => $data['measured_on'] ?? now()->toDateString(),
                'location_chainage' => $data['location_chainage'] ?? null,
                'nos' => $data['nos'] ?? 1.000,
                'length' => $data['length'] ?? null,
                'breadth' => $data['breadth'] ?? null,
                'depth_height' => $data['depth_height'] ?? null,
                'direct_quantity' => $data['direct_quantity'] ?? null,
                'hold_quantity' => $data['hold_quantity'] ?? 0.000,
                'photo_path' => $photoPath,
                'remarks' => $data['remarks'] ?? null,
                'status' => $data['status'] ?? 'verified',
                'recorded_by' => $actor->id,
                'verified_by' => ($data['status'] ?? 'verified') === 'verified' ? $actor->id : null,
                'verified_at' => ($data['status'] ?? 'verified') === 'verified' ? now() : null,
            ]);

            AuditLog::record(
                'work_order.measurement.recorded',
                $entry,
                [],
                [
                    'work_order_id' => $workOrder->id,
                    'sr_no' => $woItem->sr_no,
                    'computed_quantity' => (float) $entry->computed_quantity,
                    'hold_quantity' => (float) $entry->hold_quantity,
                ],
                "Recorded site measurement for {$woItem->sr_no}"
            );

            return $entry;
        });
    }

    /**
     * Create a new RA Bill or recalculate an existing unapproved RA Bill.
     */
    public function createOrRecalculateRaBill(
        WorkOrder $workOrder,
        array $data,
        User $actor,
        ?RaBill $existingBill = null
    ): RaBill {
        return DB::transaction(function () use ($workOrder, $data, $actor, $existingBill) {
            $workOrder->load(['items', 'company']);

            if ($workOrder->items->isEmpty()) {
                throw new InvalidArgumentException('Cannot generate an RA Bill for a Work Order without structured line items.');
            }

            if ($existingBill) {
                if ($existingBill->isLocked()) {
                    throw new RuntimeException('Approved or invoiced RA Bills are locked and cannot be recalculated.');
                }
                $raNumber = (int) $existingBill->ra_number;
                $raBill = $existingBill;
            } else {
                // Ensure no other active unapproved RA bill exists on this work order
                $openBillExists = RaBill::where('work_order_id', $workOrder->id)
                    ->whereIn('status', ['draft', 'submitted', 'site_manager_approved'])
                    ->exists();

                if ($openBillExists) {
                    throw new RuntimeException('An unapproved RA Bill already exists for this Work Order. Approve or reject it before creating the next RA Bill.');
                }

                $lastRaNumber = (int) RaBill::where('work_order_id', $workOrder->id)
                    ->where('status', '!=', 'rejected')
                    ->max('ra_number');

                $raNumber = $lastRaNumber + 1;
                $raBill = new RaBill([
                    'company_id' => $workOrder->company_id,
                    'work_order_id' => $workOrder->id,
                    'site_id' => $workOrder->site_id,
                    'client_id' => $workOrder->client_id,
                    'ra_number' => $raNumber,
                    'ra_label' => $data['ra_label'] ?? RaBill::formatOrdinalLabel($raNumber),
                    'status' => $data['status'] ?? 'draft',
                    'created_by' => $actor->id,
                ]);
            }

            $periodFrom = $data['period_from'] ?? ($existingBill?->period_from?->toDateString() ?? now()->startOfMonth()->toDateString());
            $periodTo = $data['period_to'] ?? ($existingBill?->period_to?->toDateString() ?? now()->toDateString());
            $cgstRate = isset($data['cgst_rate']) ? (float) $data['cgst_rate'] : (float) ($workOrder->cgst_rate ?? 9.00);
            $sgstRate = isset($data['sgst_rate']) ? (float) $data['sgst_rate'] : (float) ($workOrder->sgst_rate ?? 9.00);
            $retentionPercent = isset($data['retention_percent']) ? (float) $data['retention_percent'] : (float) ($workOrder->retention_percent ?? 0.00);
            $roundingMode = $data['rounding_mode'] ?? ($existingBill?->rounding_mode ?? ($workOrder->company?->invoice_rounding_mode ?? 'paisa'));

            $raBill->fill([
                'site_id' => $workOrder->site_id,
                'client_id' => $workOrder->client_id,
                'ra_label' => $data['ra_label'] ?? $raBill->ra_label ?? RaBill::formatOrdinalLabel($raNumber),
                'invoice_date' => $data['invoice_date'] ?? $raBill->invoice_date,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'work_order_revision' => (int) ($workOrder->current_revision ?? 0),
                'cgst_rate' => $cgstRate,
                'sgst_rate' => $sgstRate,
                'retention_percent' => $retentionPercent,
                'rounding_mode' => $roundingMode,
            ]);
            $raBill->save();

            // Find immediately preceding approved RA bill to carry forward any unbilled excess
            $prevApprovedBill = RaBill::where('work_order_id', $workOrder->id)
                ->whereIn('status', RaBill::APPROVED_STATUSES)
                ->where('ra_number', '<', $raNumber)
                ->orderByDesc('ra_number')
                ->with('items')
                ->first();

            $prevExcessByItem = [];
            if ($prevApprovedBill) {
                foreach ($prevApprovedBill->items as $prevItem) {
                    $prevExcessByItem[$prevItem->work_order_item_id] = (float) $prevItem->excess_quantity;
                }
            }

            $manualItemsInput = $data['items'] ?? [];

            $totalPreviousAmount = 0.0;
            $totalCurrentAmount = 0.0;
            $totalCumulativeAmount = 0.0;
            $totalExcessAmount = 0.0;

            foreach ($workOrder->items as $index => $woItem) {
                // Sum of current_billable_quantity across all prior approved RA bills
                $previousQty = (float) RaBillItem::where('work_order_item_id', $woItem->id)
                    ->whereHas('raBill', function ($q) use ($workOrder, $raNumber) {
                        $q->where('work_order_id', $workOrder->id)
                            ->whereIn('status', RaBill::APPROVED_STATUSES)
                            ->where('ra_number', '<', $raNumber);
                    })
                    ->sum('current_billable_quantity');

                // Unbilled measurements in period (or already attached to this draft RA bill)
                $measurementsQuery = MeasurementEntry::where('work_order_id', $workOrder->id)
                    ->where('work_order_item_id', $woItem->id)
                    ->where(function ($q) use ($raBill, $periodFrom, $periodTo) {
                        $q->where('ra_bill_id', $raBill->id)
                            ->orWhere(function ($q2) use ($periodFrom, $periodTo) {
                                $q2->whereNull('ra_bill_id')
                                    ->whereIn('status', ['verified', 'draft'])
                                    ->whereBetween('measured_on', [$periodFrom, $periodTo]);
                            });
                    });

                $measurements = $measurementsQuery->get();
                $measuredQty = (float) $measurements->sum('computed_quantity');
                $measuredHoldQty = (float) $measurements->sum('hold_quantity');
                $carriedExcess = $prevExcessByItem[$woItem->id] ?? 0.0;

                // Determine claimed_quantity and hold_quantity (support keyed by work_order_item_id or index)
                $manualRow = $manualItemsInput[$woItem->id] ?? $manualItemsInput[$index] ?? null;

                if (is_array($manualRow) && array_key_exists('claimed_quantity', $manualRow)) {
                    $claimedQty = (float) $manualRow['claimed_quantity'];
                } else {
                    $claimedQty = round($carriedExcess + $measuredQty, 3);
                }

                if (is_array($manualRow) && array_key_exists('hold_quantity', $manualRow)) {
                    $holdQty = (float) $manualRow['hold_quantity'];
                } else {
                    $holdQty = round($measuredHoldQty, 3);
                }

                $computed = self::computeLineItemBill(
                    ordered: (float) $woItem->ordered_quantity,
                    previous: $previousQty,
                    claimed: $claimedQty,
                    hold: $holdQty,
                    rate: (float) $woItem->rate
                );

                RaBillItem::updateOrCreate(
                    [
                        'ra_bill_id' => $raBill->id,
                        'work_order_item_id' => $woItem->id,
                    ],
                    array_merge($computed, [
                        'company_id' => $workOrder->company_id,
                        'sr_no' => (string) $woItem->sr_no,
                        'service_code' => $woItem->service_code,
                        'sac_code' => $woItem->sac_code,
                        'description' => $woItem->description,
                        'specification' => $woItem->specification,
                        'unit' => $woItem->unit,
                        'remarks' => $manualRow['remarks'] ?? null,
                        'sort_order' => $woItem->sort_order ?: ($index + 1),
                    ])
                );

                if ($measurements->isNotEmpty()) {
                    MeasurementEntry::whereIn('id', $measurements->pluck('id'))
                        ->update(['ra_bill_id' => $raBill->id]);
                }

                $totalPreviousAmount = round($totalPreviousAmount + $computed['previous_amount'], 2);
                $totalCurrentAmount = round($totalCurrentAmount + $computed['current_amount'], 2);
                $totalCumulativeAmount = round($totalCumulativeAmount + $computed['cumulative_amount'], 2);
                $totalExcessAmount = round($totalExcessAmount + $computed['excess_amount'], 2);
            }

            // Standard Rounding Rule:
            // A = sum of rounded line current_amounts
            // CGST = round(A * cgst_rate / 100, 2)
            // SGST = round(A * sgst_rate / 100, 2)
            // Gross = A + CGST + SGST
            $cgstAmount = round($totalCurrentAmount * ($cgstRate / 100), 2);
            $sgstAmount = round($totalCurrentAmount * ($sgstRate / 100), 2);
            $grossAmount = round($totalCurrentAmount + $cgstAmount + $sgstAmount, 2);

            $retentionDeduction = array_key_exists('retention_deduction', $data)
                ? round((float) $data['retention_deduction'], 2)
                : round($totalCurrentAmount * ($retentionPercent / 100), 2);

            $advanceRecovery = round((float) ($data['advance_recovery'] ?? ($existingBill?->advance_recovery ?? 0)), 2);
            $otherDeductions = round((float) ($data['other_deductions'] ?? ($existingBill?->other_deductions ?? 0)), 2);

            $netBeforeRounding = round($grossAmount - $retentionDeduction - $advanceRecovery - $otherDeductions, 2);
            $netPayable = $roundingMode === 'rupee'
                ? round($netBeforeRounding, 0)
                : $netBeforeRounding;

            $raBill->update([
                'previous_bill_amount' => $totalPreviousAmount,
                'current_bill_amount' => $totalCurrentAmount,
                'cumulative_bill_amount' => $totalCumulativeAmount,
                'excess_unbilled_amount' => $totalExcessAmount,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'gross_amount' => $grossAmount,
                'retention_deduction' => $retentionDeduction,
                'advance_recovery' => $advanceRecovery,
                'other_deductions' => $otherDeductions,
                'net_payable' => $netPayable,
            ]);

            AuditLog::record(
                $existingBill ? 'ra_bill.recalculated' : 'ra_bill.created',
                $raBill,
                [],
                [
                    'ra_number' => $raBill->ra_number,
                    'ra_label' => $raBill->ra_label,
                    'current_bill_amount' => (float) $raBill->current_bill_amount,
                    'gross_amount' => (float) $raBill->gross_amount,
                    'net_payable' => (float) $raBill->net_payable,
                    'excess_unbilled_amount' => (float) $raBill->excess_unbilled_amount,
                ],
                "Calculated {$raBill->ra_label}"
            );

            return $raBill->fresh(['items', 'workOrder', 'client', 'site']);
        });
    }

    /**
     * Submit a draft RA Bill for Tier 1 (Site Manager) verification.
     */
    public function submitRaBill(RaBill $raBill, User $actor): RaBill
    {
        if ($raBill->status !== 'draft') {
            throw new RuntimeException('Only draft RA Bills can be submitted.');
        }

        $raBill->update(['status' => 'submitted']);

        AuditLog::record(
            'ra_bill.submitted',
            $raBill,
            ['status' => 'draft'],
            ['status' => 'submitted', 'submitted_by' => $actor->id],
            "Submitted {$raBill->ra_label} for Tier 1 verification"
        );

        return $raBill->fresh();
    }

    /**
     * Tier 1: Site Manager verification of an RA Bill.
     */
    public function approveSiteManager(RaBill $raBill, User $actor, ?string $notes = null): RaBill
    {
        if (! in_array($raBill->status, ['draft', 'submitted'], true)) {
            throw new RuntimeException('RA Bill is not awaiting Site Manager verification.');
        }

        $oldStatus = $raBill->status;
        $raBill->update([
            'status' => 'site_manager_approved',
            'site_manager_id' => $actor->id,
            'site_manager_approved_at' => now(),
            'site_manager_notes' => $notes,
        ]);

        AuditLog::record(
            'ra_bill.site_manager_approved',
            $raBill,
            ['status' => $oldStatus],
            ['status' => 'site_manager_approved', 'site_manager_id' => $actor->id],
            "Site Manager verified {$raBill->ra_label}"
        );

        return $raBill->fresh();
    }

    /**
     * Tier 2: Senior Manager final approval of an RA Bill.
     * Enforces non-self-approval and locks linked measurement entries as 'billed'.
     */
    public function approveSeniorManager(RaBill $raBill, User $actor, ?string $notes = null): RaBill
    {
        if ($raBill->status !== 'site_manager_approved') {
            throw new RuntimeException('RA Bill must be verified by the Site Manager (Tier 1) before Senior Manager approval.');
        }

        if ((int) $raBill->site_manager_id === (int) $actor->id) {
            throw new RuntimeException('Non-self-approval rule: The user who verified Tier 1 cannot perform Tier 2 final approval.');
        }

        return DB::transaction(function () use ($raBill, $actor, $notes) {
            $raBill->update([
                'status' => 'approved',
                'senior_manager_id' => $actor->id,
                'senior_manager_approved_at' => now(),
                'senior_manager_notes' => $notes,
            ]);

            app(RaBillExportService::class)->ensureInvoiceNumberAssigned($raBill);

            MeasurementEntry::where('ra_bill_id', $raBill->id)
                ->update([
                    'status' => 'billed',
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                ]);

            AuditLog::record(
                'ra_bill.approved',
                $raBill,
                ['status' => 'site_manager_approved'],
                ['status' => 'approved', 'senior_manager_id' => $actor->id],
                "Senior Manager approved {$raBill->ra_label}"
            );

            return $raBill->fresh(['items']);
        });
    }

    /**
     * Reject an RA Bill and release its measurement entries back to unbilled pool.
     */
    public function rejectRaBill(RaBill $raBill, User $actor, string $reason): RaBill
    {
        if ($raBill->isLocked()) {
            throw new RuntimeException('Approved or invoiced RA Bills cannot be rejected.');
        }

        return DB::transaction(function () use ($raBill, $actor, $reason) {
            $oldStatus = $raBill->status;

            MeasurementEntry::where('ra_bill_id', $raBill->id)
                ->update([
                    'ra_bill_id' => null,
                    'status' => 'verified',
                ]);

            $raBill->update([
                'status' => 'rejected',
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::record(
                'ra_bill.rejected',
                $raBill,
                ['status' => $oldStatus],
                ['status' => 'rejected', 'rejection_reason' => $reason],
                "Rejected {$raBill->ra_label}"
            );

            return $raBill->fresh();
        });
    }
}
