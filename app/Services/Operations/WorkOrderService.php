<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderRevision;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class WorkOrderService
{
    public function createWorkOrder(array $data, User $creator): WorkOrder
    {
        $companyId = (int) TenantContext::getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Tenant context not established.');
        }

        $year = (int) Carbon::now()->format('Y');

        return DB::transaction(function () use ($companyId, $data, $creator, $year) {
            $orderNumber = $this->generateNextOrderNumber($companyId, $year);

            $rawLines = !empty($data['items']) && is_array($data['items'])
                ? $data['items']
                : (!empty($data['boq_items']) && is_array($data['boq_items']) ? $data['boq_items'] : []);

            $normalizedLines = [];
            $boqItems = [];
            $baseOrderValue = 0.00;

            foreach (array_values($rawLines) as $idx => $item) {
                $desc = trim((string) ($item['description'] ?? ''));
                if ($desc === '') {
                    continue;
                }

                $srNo = isset($item['sr_no']) && trim((string) $item['sr_no']) !== ''
                    ? trim((string) $item['sr_no'])
                    : (string) ($idx + 1);
                $serviceCode = isset($item['service_code']) && trim((string) $item['service_code']) !== ''
                    ? trim((string) $item['service_code'])
                    : null;
                $sacCode = isset($item['sac_code']) && trim((string) $item['sac_code']) !== ''
                    ? trim((string) $item['sac_code'])
                    : null;
                $spec = isset($item['specification']) && trim((string) $item['specification']) !== ''
                    ? trim((string) $item['specification'])
                    : null;
                $unit = trim((string) ($item['unit'] ?? 'Cum')) ?: 'Cum';
                $qty = round(max(0.0, (float) ($item['ordered_quantity'] ?? $item['quantity'] ?? 0)), 3);
                $rate = round(max(0.0, (float) ($item['rate'] ?? 0)), 2);
                $lineAmount = round($qty * $rate, 2);
                $baseOrderValue = round($baseOrderValue + $lineAmount, 2);

                $normalizedLines[] = [
                    'sr_no' => $srNo,
                    'service_code' => $serviceCode,
                    'sac_code' => $sacCode,
                    'description' => $desc,
                    'specification' => $spec,
                    'unit' => $unit,
                    'ordered_quantity' => $qty,
                    'rate' => $rate,
                    'line_amount' => $lineAmount,
                    'completion_date' => $item['completion_date'] ?? null,
                    'sort_order' => $idx + 1,
                ];

                $boqItems[] = [
                    'sr_no' => $srNo,
                    'service_code' => $serviceCode,
                    'sac_code' => $sacCode,
                    'description' => $desc,
                    'specification' => $spec,
                    'unit' => $unit,
                    'quantity' => $qty,
                    'rate' => $rate,
                    'amount' => $lineAmount,
                ];
            }

            $estimatedAmount = $baseOrderValue > 0
                ? $baseOrderValue
                : (isset($data['estimated_amount']) && (float) $data['estimated_amount'] > 0
                    ? round((float) $data['estimated_amount'], 2)
                    : 0.00);

            $wo = new WorkOrder();
            $wo->company_id = $companyId;
            $wo->project_id = $data['project_id'];
            $wo->site_id = $data['site_id'] ?? null;
            $wo->order_type = $data['order_type'] ?? 'client_issued';
            $wo->order_number = $orderNumber;
            $wo->client_reference = $data['client_reference'] ?? ($data['po_number'] ?? null);

            if (Schema::hasColumn('work_orders', 'client_id')) {
                $wo->client_id = $data['client_id'] ?? null;
                $wo->po_number = $data['po_number'] ?? ($data['client_reference'] ?? null);
                $wo->po_date = $data['po_date'] ?? null;
                $wo->job_location = $data['job_location'] ?? null;
                $wo->user_department_person = $data['user_department_person'] ?? null;
                $wo->effective_date = $data['effective_date'] ?? ($data['target_start_date'] ?? null);
                $wo->completion_date = $data['completion_date'] ?? ($data['target_completion_date'] ?? null);
                $wo->cgst_rate = isset($data['cgst_rate']) ? round((float) $data['cgst_rate'], 2) : 9.00;
                $wo->sgst_rate = isset($data['sgst_rate']) ? round((float) $data['sgst_rate'], 2) : 9.00;
                $wo->retention_percent = isset($data['retention_percent']) ? round((float) $data['retention_percent'], 2) : 5.00;
                $wo->current_revision = 0;
                $wo->base_order_value = $estimatedAmount;
            }

            if (Schema::hasColumn('work_orders', 'contractor_name')) {
                $wo->contractor_name = $data['contractor_name'] ?? null;
                $wo->contractor_gst = $data['contractor_gst'] ?? null;
                $wo->contractor_contact = $data['contractor_contact'] ?? null;
                $wo->payment_terms = $data['payment_terms'] ?? WorkOrder::DEFAULT_PAYMENT_TERMS;
                $wo->boq_items = !empty($boqItems) ? $boqItems : null;
                $wo->terms_and_conditions = $data['terms_and_conditions'] ?? WorkOrder::DEFAULT_TERMS;
            }

            $wo->title = $data['title'];
            $wo->description = $data['description'] ?? null;
            $wo->scope_of_work = $data['scope_of_work'] ?? null;
            $wo->estimated_amount = $estimatedAmount;
            $wo->target_start_date = $data['target_start_date'] ?? ($data['effective_date'] ?? null);
            $wo->target_completion_date = $data['target_completion_date'] ?? ($data['completion_date'] ?? null);
            $wo->status = 'submitted';
            $wo->created_by = $creator->id;
            $wo->save();

            if (!empty($normalizedLines) && Schema::hasTable('work_order_items')) {
                foreach ($normalizedLines as $line) {
                    WorkOrderItem::create(array_merge($line, [
                        'company_id' => $companyId,
                        'work_order_id' => $wo->id,
                    ]));
                }
            }

            if (Schema::hasTable('work_order_revisions')) {
                WorkOrderRevision::create([
                    'company_id' => $companyId,
                    'work_order_id' => $wo->id,
                    'revision_number' => 0,
                    'reason' => 'Initial Work Order Registration',
                    'previous_base_value' => 0.00,
                    'new_base_value' => $estimatedAmount,
                    'items_snapshot' => $normalizedLines,
                    'proposed_items' => $normalizedLines,
                    'status' => 'approved',
                    'changed_by' => $creator->id,
                    'approved_by' => $creator->id,
                    'approved_at' => now(),
                ]);
            }

            return $wo->fresh(['items', 'revisions', 'client']);
        });
    }

    /**
     * Create a new revision for an existing Work Order (e.g. JSW PO amendment raising ordered quantities).
     * When created by Office Staff or Site Manager, status is 'pending_approval' until approved by a Senior Manager / Admin.
     */
    public function createRevision(
        WorkOrder $wo,
        array $itemsData,
        string $reason,
        User $actor,
        bool $autoApprove = false
    ): WorkOrderRevision {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A revision reason is mandatory.');
        }

        return DB::transaction(function () use ($wo, $itemsData, $reason, $actor, $autoApprove) {
            $currentItems = $wo->items()->orderBy('sort_order')->orderBy('id')->get();
            $snapshot = $currentItems->map(fn (WorkOrderItem $item) => [
                'id' => $item->id,
                'sr_no' => (string) $item->sr_no,
                'service_code' => $item->service_code,
                'sac_code' => $item->sac_code,
                'description' => $item->description,
                'specification' => $item->specification,
                'unit' => $item->unit,
                'ordered_quantity' => (float) $item->ordered_quantity,
                'rate' => (float) $item->rate,
                'line_amount' => (float) $item->line_amount,
            ])->values()->all();

            $proposed = [];
            $newBaseValue = 0.00;

            foreach (array_values($itemsData) as $idx => $row) {
                $desc = trim((string) ($row['description'] ?? ''));
                if ($desc === '') {
                    continue;
                }
                $srNo = isset($row['sr_no']) && trim((string) $row['sr_no']) !== ''
                    ? trim((string) $row['sr_no'])
                    : (string) ($idx + 1);
                $qty = round(max(0.0, (float) ($row['ordered_quantity'] ?? $row['quantity'] ?? 0)), 3);
                $rate = round(max(0.0, (float) ($row['rate'] ?? 0)), 2);
                $lineAmount = round($qty * $rate, 2);
                $newBaseValue = round($newBaseValue + $lineAmount, 2);

                $proposed[] = [
                    'id' => isset($row['id']) ? (int) $row['id'] : null,
                    'sr_no' => $srNo,
                    'service_code' => isset($row['service_code']) && trim((string) $row['service_code']) !== '' ? trim((string) $row['service_code']) : null,
                    'sac_code' => isset($row['sac_code']) && trim((string) $row['sac_code']) !== '' ? trim((string) $row['sac_code']) : null,
                    'description' => $desc,
                    'specification' => isset($row['specification']) && trim((string) $row['specification']) !== '' ? trim((string) $row['specification']) : null,
                    'unit' => trim((string) ($row['unit'] ?? 'Cum')) ?: 'Cum',
                    'ordered_quantity' => $qty,
                    'rate' => $rate,
                    'line_amount' => $lineAmount,
                    'completion_date' => $row['completion_date'] ?? null,
                    'sort_order' => $idx + 1,
                ];
            }

            if (empty($proposed)) {
                throw new InvalidArgumentException('At least one valid work order line item is required for a revision.');
            }

            $maxRev = (int) WorkOrderRevision::withoutGlobalScopes()
                ->where('work_order_id', $wo->id)
                ->max('revision_number');
            $nextRev = $maxRev + 1;

            $previousBase = (float) ($wo->base_order_value > 0 ? $wo->base_order_value : $wo->estimated_amount);

            $revision = WorkOrderRevision::create([
                'company_id' => $wo->company_id,
                'work_order_id' => $wo->id,
                'revision_number' => $nextRev,
                'reason' => trim($reason),
                'previous_base_value' => $previousBase,
                'new_base_value' => $newBaseValue,
                'items_snapshot' => $snapshot,
                'proposed_items' => $proposed,
                'status' => $autoApprove ? 'approved' : 'pending_approval',
                'changed_by' => $actor->id,
                'approved_by' => $autoApprove ? $actor->id : null,
                'approved_at' => $autoApprove ? now() : null,
            ]);

            if ($autoApprove) {
                $this->applyApprovedRevisionItems($wo, $revision);
            }

            AuditLog::record(
                'work_order.revision_created',
                $wo,
                ['revision' => $wo->current_revision, 'base_order_value' => $previousBase],
                ['revision_number' => $nextRev, 'new_base_value' => $newBaseValue, 'status' => $revision->status],
                "Work order revision #{$nextRev} recorded: {$reason}"
            );

            return $revision;
        });
    }

    /**
     * Approve a pending WorkOrderRevision (enforces non-self-approval).
     */
    public function approveRevision(WorkOrder $wo, WorkOrderRevision $revision, User $approver): WorkOrderRevision
    {
        if ((int) $revision->work_order_id !== (int) $wo->id) {
            throw new InvalidArgumentException('Revision does not belong to this work order.');
        }

        if ($revision->status !== 'pending_approval') {
            throw new InvalidArgumentException('Only pending revisions can be approved.');
        }

        if ((int) $revision->changed_by === (int) $approver->id) {
            throw new InvalidArgumentException('You cannot approve your own work order revision.');
        }

        return DB::transaction(function () use ($wo, $revision, $approver) {
            $revision->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->applyApprovedRevisionItems($wo, $revision);

            AuditLog::record(
                'work_order.revision_approved',
                $wo,
                ['previous_base_value' => $revision->previous_base_value],
                ['revision_number' => $revision->revision_number, 'new_base_value' => $revision->new_base_value],
                "Approved work order revision #{$revision->revision_number}"
            );

            return $revision->fresh();
        });
    }

    protected function applyApprovedRevisionItems(WorkOrder $wo, WorkOrderRevision $revision): void
    {
        $proposed = $revision->proposed_items ?? [];
        $boqItems = [];
        $keptIds = [];

        foreach ($proposed as $idx => $itemData) {
            $itemModel = null;
            if (!empty($itemData['id'])) {
                $itemModel = WorkOrderItem::withoutGlobalScopes()
                    ->where('work_order_id', $wo->id)
                    ->where('id', (int) $itemData['id'])
                    ->first();
            }

            if (!$itemModel) {
                $itemModel = WorkOrderItem::withoutGlobalScopes()
                    ->where('work_order_id', $wo->id)
                    ->where('sr_no', (string) $itemData['sr_no'])
                    ->first();
            }

            $payload = [
                'company_id' => $wo->company_id,
                'work_order_id' => $wo->id,
                'sr_no' => (string) $itemData['sr_no'],
                'service_code' => $itemData['service_code'] ?? null,
                'sac_code' => $itemData['sac_code'] ?? null,
                'description' => $itemData['description'],
                'specification' => $itemData['specification'] ?? null,
                'unit' => $itemData['unit'] ?? 'Cum',
                'ordered_quantity' => round((float) $itemData['ordered_quantity'], 3),
                'rate' => round((float) $itemData['rate'], 2),
                'line_amount' => round((float) $itemData['ordered_quantity'] * (float) $itemData['rate'], 2),
                'completion_date' => $itemData['completion_date'] ?? null,
                'sort_order' => $idx + 1,
            ];

            if ($itemModel) {
                $itemModel->update($payload);
            } else {
                $itemModel = WorkOrderItem::create($payload);
            }

            $keptIds[] = $itemModel->id;
            $boqItems[] = [
                'sr_no' => $itemModel->sr_no,
                'service_code' => $itemModel->service_code,
                'sac_code' => $itemModel->sac_code,
                'description' => $itemModel->description,
                'specification' => $itemModel->specification,
                'unit' => $itemModel->unit,
                'quantity' => (float) $itemModel->ordered_quantity,
                'rate' => (float) $itemModel->rate,
                'amount' => (float) $itemModel->line_amount,
            ];
        }

        $wo->update([
            'current_revision' => $revision->revision_number,
            'base_order_value' => $revision->new_base_value,
            'estimated_amount' => $revision->new_base_value,
            'boq_items' => $boqItems,
        ]);

        // If an unapproved draft/submitted RA bill exists on this work order, recalculate it so revised quantities unlock earlier excess
        $openRaBills = \App\Models\RaBill::where('work_order_id', $wo->id)
            ->whereIn('status', ['draft', 'submitted'])
            ->with('items')
            ->get();

        foreach ($openRaBills as $openBill) {
            $preservedItems = [];
            foreach ($openBill->items as $billItem) {
                $preservedItems[$billItem->work_order_item_id] = [
                    'claimed_quantity' => (float) $billItem->claimed_quantity,
                    'hold_quantity' => (float) $billItem->hold_quantity,
                    'remarks' => $billItem->remarks,
                ];
            }

            app(RaBillingService::class)->createOrRecalculateRaBill(
                workOrder: $wo->fresh(['items', 'company']),
                data: ['items' => $preservedItems],
                actor: $revision->approver ?? $wo->creator,
                existingBill: $openBill
            );
        }
    }

    /**
     * Atomically generate the next unique work order number (WO-NSD-YYYY-###) using a locked sequence row.
     */
    protected function generateNextOrderNumber(int $companyId, int $year): string
    {
        $prefix = 'WO-NSD';
        $pattern = sprintf('%s-%04d-', $prefix, $year);

        $existingCodes = WorkOrder::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('order_number', 'like', $pattern . '%')
            ->pluck('order_number');

        $maxExisting = 0;
        foreach ($existingCodes as $code) {
            if (preg_match('/^' . preg_quote($pattern, '/') . '(\d+)$/', (string) $code, $m)) {
                $val = (int) $m[1];
                if ($val > $maxExisting) {
                    $maxExisting = $val;
                }
            }
        }

        if (Schema::hasTable('worker_sequences')) {
            $row = DB::table('worker_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $nextNumber = max((int) $row->last_number, $maxExisting) + 1;
                DB::table('worker_sequences')
                    ->where('id', $row->id)
                    ->update([
                        'last_number' => $nextNumber,
                        'updated_at' => now(),
                    ]);
            } else {
                $nextNumber = $maxExisting + 1;
                DB::table('worker_sequences')->insert([
                    'company_id' => $companyId,
                    'prefix' => $prefix,
                    'year' => $year,
                    'last_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            $nextNumber = $maxExisting + 1;
        }

        return sprintf('%s-%04d-%03d', $prefix, $year, $nextNumber);
    }

    public function approveBySiteManager(WorkOrder $wo, User $manager, ?string $notes = null): WorkOrder
    {
        if ($wo->status !== 'submitted') {
            throw new InvalidArgumentException('Work order must be in submitted status for site manager approval.');
        }

        return DB::transaction(function () use ($wo, $manager, $notes) {
            $wo->status = 'site_manager_approved';
            $wo->site_manager_id = $manager->id;
            $wo->site_manager_approved_at = Carbon::now();
            $wo->site_manager_notes = $notes;
            $wo->save();

            WorkOrderApproval::create([
                'work_order_id' => $wo->id,
                'stage' => 'site_manager',
                'action' => 'approved',
                'actor_id' => $manager->id,
                'comments' => $notes,
            ]);

            return $wo;
        });
    }

    public function approveBySeniorManager(WorkOrder $wo, User $seniorManager, ?string $notes = null): WorkOrder
    {
        if ($wo->status !== 'site_manager_approved') {
            throw new InvalidArgumentException('Work order must be approved by site manager before senior manager approval.');
        }

        return DB::transaction(function () use ($wo, $seniorManager, $notes) {
            $wo->status = 'senior_manager_approved';
            $wo->senior_manager_id = $seniorManager->id;
            $wo->senior_manager_approved_at = Carbon::now();
            $wo->senior_manager_notes = $notes;
            $wo->save();

            WorkOrderApproval::create([
                'work_order_id' => $wo->id,
                'stage' => 'senior_manager',
                'action' => 'approved',
                'actor_id' => $seniorManager->id,
                'comments' => $notes,
            ]);

            return $wo;
        });
    }

    public function rejectWorkOrder(WorkOrder $wo, User $actor, string $reason): WorkOrder
    {
        if (!in_array($wo->status, ['submitted', 'site_manager_approved'], true)) {
            throw new InvalidArgumentException('Work order cannot be rejected in its current status.');
        }

        return DB::transaction(function () use ($wo, $actor, $reason) {
            $stage = $wo->status === 'submitted' ? 'site_manager' : 'senior_manager';
            $wo->status = 'rejected';
            $wo->rejected_by = $actor->id;
            $wo->rejected_at = Carbon::now();
            $wo->rejection_reason = $reason;
            $wo->save();

            WorkOrderApproval::create([
                'work_order_id' => $wo->id,
                'stage' => $stage,
                'action' => 'rejected',
                'actor_id' => $actor->id,
                'comments' => $reason,
            ]);

            return $wo;
        });
    }
}
