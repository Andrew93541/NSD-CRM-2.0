<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class InventoryService
{
    public function createPurchaseRequest(array $data, array $items, User $requester): PurchaseRequest
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);

        if (! empty($data['is_emergency']) && empty($data['emergency_justification'])) {
            throw new InvalidArgumentException('Emergency purchase justification is mandatory for emergency requests.');
        }

        return DB::transaction(function () use ($companyId, $token, $data, $items, $requester) {
            $reqNumber = $this->generateNextDocumentNumber(
                $companyId,
                'purchase_request',
                "PR-{$token}",
                fn (string $code) => PurchaseRequest::withoutGlobalScopes()->where('company_id', $companyId)->where('request_number', $code)->exists(),
                fn () => PurchaseRequest::withoutGlobalScopes()->where('company_id', $companyId)->count()
            );

            $pr = PurchaseRequest::create([
                'company_id' => $companyId,
                'site_id' => $data['site_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'request_number' => $reqNumber,
                'requested_by' => $requester->id,
                'urgency' => $data['urgency'] ?? 'normal',
                'is_emergency' => ! empty($data['is_emergency']),
                'emergency_justification' => $data['emergency_justification'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Requested material quantity must be greater than zero.');
                }

                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'material_id' => $item['material_id'],
                    'quantity_requested' => $qty,
                    'estimated_unit_cost' => max(0.00, (float) ($item['estimated_unit_cost'] ?? 0.00)),
                    'purpose_notes' => $item['purpose_notes'] ?? null,
                ]);
            }

            return $pr;
        });
    }

    public function approvePurchaseRequest(PurchaseRequest $pr, User $approver, ?string $comments = null, bool $enforceSeparationOfDuties = false): PurchaseRequest
    {
        return DB::transaction(function () use ($pr, $approver, $comments, $enforceSeparationOfDuties) {
            $lockedPr = PurchaseRequest::withoutGlobalScopes()
                ->where('id', $pr->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPr->status !== 'pending') {
                throw new InvalidArgumentException('Purchase request is not pending approval.');
            }

            if ($enforceSeparationOfDuties && (int) $lockedPr->requested_by === (int) $approver->id) {
                throw new InvalidArgumentException('Requesters cannot approve their own purchase request.');
            }

            $lockedPr->status = 'approved';
            $lockedPr->approved_by = $approver->id;
            $lockedPr->approved_at = Carbon::now();
            $lockedPr->review_comments = $comments;
            $lockedPr->save();

            return $lockedPr;
        });
    }

    public function rejectPurchaseRequest(PurchaseRequest $pr, User $reviewer, string $reason): PurchaseRequest
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($pr, $reviewer, $cleanReason) {
            $lockedPr = PurchaseRequest::withoutGlobalScopes()
                ->where('id', $pr->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPr->status !== 'pending') {
                throw new InvalidArgumentException('Only pending purchase requests can be rejected.');
            }

            if ((int) $lockedPr->requested_by === (int) $reviewer->id) {
                throw new InvalidArgumentException('Requesters cannot review their own purchase request.');
            }

            $lockedPr->status = 'rejected';
            $lockedPr->approved_by = $reviewer->id;
            $lockedPr->approved_at = Carbon::now();
            $lockedPr->rejection_reason = $cleanReason;
            $lockedPr->save();

            return $lockedPr;
        });
    }

    public function cancelPurchaseRequest(PurchaseRequest $pr, User $actor, ?string $reason = null): PurchaseRequest
    {
        return DB::transaction(function () use ($pr, $actor, $reason) {
            $lockedPr = PurchaseRequest::withoutGlobalScopes()
                ->where('id', $pr->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPr->status !== 'pending') {
                throw new InvalidArgumentException('Only pending purchase requests can be cancelled.');
            }

            $lockedPr->status = 'cancelled';
            $lockedPr->review_comments = $reason ? "Cancelled by {$actor->name}: ".trim($reason) : "Cancelled by {$actor->name}";
            $lockedPr->save();

            return $lockedPr;
        });
    }

    public function createPurchaseOrder(PurchaseRequest $pr, array $data, User $creator): PurchaseOrder
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);

        return DB::transaction(function () use ($companyId, $token, $pr, $data, $creator) {
            $lockedPr = PurchaseRequest::withoutGlobalScopes()
                ->with('items')
                ->where('id', $pr->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPr->status !== 'approved') {
                throw new InvalidArgumentException('Purchase request must be approved before issuing purchase order.');
            }

            $poNumber = $this->generateNextDocumentNumber(
                $companyId,
                'purchase_order',
                "PO-{$token}",
                fn (string $code) => PurchaseOrder::withoutGlobalScopes()->where('company_id', $companyId)->where('po_number', $code)->exists(),
                fn () => PurchaseOrder::withoutGlobalScopes()->where('company_id', $companyId)->count()
            );

            $totalAmount = 0.00;
            $itemsData = [];

            foreach ($lockedPr->items as $reqItem) {
                $qty = (float) $reqItem->quantity_requested;
                $unitPrice = (float) ($data['unit_prices'][$reqItem->material_id] ?? $reqItem->estimated_unit_cost);
                if ($unitPrice < 0) {
                    throw new InvalidArgumentException('Purchase order unit price cannot be negative.');
                }

                $totalAmount += ($qty * $unitPrice);
                $itemsData[] = [
                    'material_id' => $reqItem->material_id,
                    'quantity_ordered' => $qty,
                    'unit_price' => $unitPrice,
                    'quantity_received' => 0.00,
                ];
            }

            $poPayload = [
                'company_id' => $companyId,
                'purchase_request_id' => $lockedPr->id,
                'site_id' => $lockedPr->site_id,
                'warehouse_id' => $lockedPr->warehouse_id,
                'po_number' => $poNumber,
                'supplier_name' => $data['supplier_name'],
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'order_date' => Carbon::now()->format('Y-m-d'),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'total_amount' => $totalAmount,
                'status' => 'issued',
                'created_by' => $creator->id,
                'notes' => $data['notes'] ?? null,
            ];

            if (! empty($data['vendor_id']) && Schema::hasColumn('purchase_orders', 'vendor_id')) {
                $poPayload['vendor_id'] = (int) $data['vendor_id'];
            }

            $po = PurchaseOrder::create($poPayload);

            foreach ($itemsData as $item) {
                $item['purchase_order_id'] = $po->id;
                PurchaseOrderItem::create($item);
            }

            $lockedPr->status = 'ordered';
            $lockedPr->save();

            return $po;
        });
    }

    public function recordGoodsReceipt(PurchaseOrder $po, int $destinationWarehouseId, array $receivedQuantities, User $receiver, ?string $challan = null): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);

        return DB::transaction(function () use ($companyId, $token, $po, $destinationWarehouseId, $receivedQuantities, $receiver, $challan) {
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPo->status, ['issued', 'partially_received'], true)) {
                throw new InvalidArgumentException('Purchase order is already fully received or invalid for receipt.');
            }

            if (! empty($challan)) {
                $duplicateChallan = InventoryTransaction::where('purchase_order_id', $lockedPo->id)
                    ->where('challan_or_invoice_no', trim($challan))
                    ->where('status', 'confirmed')
                    ->exists();

                if ($duplicateChallan) {
                    throw new InvalidArgumentException("Challan/invoice {$challan} has already been received for this purchase order.");
                }
            }

            $txNumber = $this->generateNextDocumentNumber(
                $companyId,
                'goods_receipt',
                "GRN-{$token}",
                fn (string $code) => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->where('transaction_number', $code)->exists(),
                fn () => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->count()
            );

            $tx = InventoryTransaction::create([
                'company_id' => $companyId,
                'transaction_number' => $txNumber,
                'transaction_type' => 'goods_receipt',
                'destination_warehouse_id' => $destinationWarehouseId,
                'site_id' => $lockedPo->site_id,
                'purchase_order_id' => $lockedPo->id,
                'challan_or_invoice_no' => $challan ? trim($challan) : null,
                'transaction_date' => Carbon::now()->format('Y-m-d'),
                'performed_by' => $receiver->id,
                'confirmed_by' => $receiver->id,
                'confirmed_at' => Carbon::now(),
                'status' => 'confirmed',
                'notes' => 'Goods receipt confirmed from PO '.$lockedPo->po_number,
            ]);

            $allCompleted = true;
            $anyReceived = false;

            $poItems = PurchaseOrderItem::where('purchase_order_id', $lockedPo->id)->lockForUpdate()->get();

            foreach ($poItems as $poItem) {
                $recQty = (float) ($receivedQuantities[$poItem->material_id] ?? 0.00);
                if ($recQty < 0) {
                    throw new InvalidArgumentException('Received quantity cannot be negative.');
                }

                if ($recQty > 0) {
                    $remaining = max(0.00, (float) $poItem->quantity_ordered - (float) $poItem->quantity_received);
                    if ($recQty > $remaining + 0.0001) {
                        throw new InvalidArgumentException(
                            "Received quantity ({$recQty}) exceeds outstanding balance ({$remaining}) for item #{$poItem->material_id}."
                        );
                    }

                    $stock = InventoryStock::where('company_id', $companyId)
                        ->where('warehouse_id', $destinationWarehouseId)
                        ->where('material_id', $poItem->material_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $stock) {
                        $stock = InventoryStock::create([
                            'company_id' => $companyId,
                            'warehouse_id' => $destinationWarehouseId,
                            'material_id' => $poItem->material_id,
                            'quantity' => 0.00,
                            'reserved_quantity' => 0.00,
                        ]);
                    }

                    $prevQty = (float) $stock->quantity;
                    $stock->quantity = round($prevQty + $recQty, 2);
                    $stock->save();

                    $this->createTransactionItem($tx->id, (int) $poItem->material_id, $recQty, (float) $poItem->unit_price, $prevQty, (float) $stock->quantity);

                    $poItem->quantity_received += $recQty;
                    $poItem->save();
                    $anyReceived = true;
                }

                if ($poItem->quantity_received < $poItem->quantity_ordered) {
                    $allCompleted = false;
                }
            }

            if (! $anyReceived) {
                throw new InvalidArgumentException('At least one item must have a quantity greater than zero to confirm goods receipt.');
            }

            $lockedPo->status = $allCompleted ? 'received' : 'partially_received';
            $lockedPo->save();

            return $tx;
        });
    }

    /**
     * Issue materials from a warehouse to a construction site (and optional work order).
     */
    public function recordStockIssue(array $data, array $items, User $actor): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);
        $submissionToken = isset($data['submission_token']) ? trim((string) $data['submission_token']) : null;

        $this->acquireSubmissionGuard($companyId, $submissionToken);

        try {
            return DB::transaction(function () use ($companyId, $token, $data, $items, $actor) {
                $sourceWarehouse = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', (int) ($data['source_warehouse_id'] ?? 0))
                    ->first();

                if (! $sourceWarehouse || ! $sourceWarehouse->is_active) {
                    throw new InvalidArgumentException('Selected source storage location is invalid or inactive.');
                }

                $site = Site::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', (int) ($data['site_id'] ?? 0))
                    ->first();

                if (! $site) {
                    throw new InvalidArgumentException('Destination construction site is required for stock issue.');
                }

                $workOrder = null;
                if (! empty($data['work_order_id'])) {
                    $workOrder = WorkOrder::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('id', (int) $data['work_order_id'])
                        ->first();

                    if (! $workOrder || ($workOrder->site_id && (int) $workOrder->site_id !== (int) $site->id)) {
                        throw new InvalidArgumentException('Selected work order does not belong to the destination site.');
                    }
                }

                $reference = ! empty($data['challan_or_invoice_no']) ? trim((string) $data['challan_or_invoice_no']) : null;
                $this->assertUniqueMovementReference($companyId, 'warehouse_issue', $reference);

                $normalizedItems = $this->normalizePositiveMovementItems($companyId, $items);

                $txNumber = $this->generateNextDocumentNumber(
                    $companyId,
                    'warehouse_issue',
                    "ISS-{$token}",
                    fn (string $code) => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->where('transaction_number', $code)->exists(),
                    fn () => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->count()
                );

                $txPayload = [
                    'company_id' => $companyId,
                    'transaction_number' => $txNumber,
                    'transaction_type' => 'warehouse_issue',
                    'source_warehouse_id' => $sourceWarehouse->id,
                    'destination_warehouse_id' => null,
                    'site_id' => $site->id,
                    'challan_or_invoice_no' => $reference,
                    'transaction_date' => ! empty($data['transaction_date']) ? $data['transaction_date'] : Carbon::now()->format('Y-m-d'),
                    'performed_by' => $actor->id,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => Carbon::now(),
                    'status' => 'confirmed',
                    'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : "Issued to {$site->name}",
                ];

                if ($workOrder && Schema::hasColumn('inventory_transactions', 'work_order_id')) {
                    $txPayload['work_order_id'] = $workOrder->id;
                }

                $tx = InventoryTransaction::create($txPayload);

                foreach ($normalizedItems as $materialId => $entry) {
                    /** @var Material $material */
                    $material = $entry['material'];
                    $qty = $entry['quantity'];

                    $stock = InventoryStock::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('warehouse_id', $sourceWarehouse->id)
                        ->where('material_id', $materialId)
                        ->lockForUpdate()
                        ->first();

                    $available = $stock ? ((float) $stock->quantity - (float) $stock->reserved_quantity) : 0.0;
                    if (! $stock || $available + 0.0001 < $qty) {
                        throw new InvalidArgumentException(
                            "Insufficient stock for {$material->name} in {$sourceWarehouse->name}. Available: ".number_format(max(0.0, $available), 2)." {$material->unit_of_measure}, requested: ".number_format($qty, 2).'.'
                        );
                    }

                    $prevQty = (float) $stock->quantity;
                    $newQty = round($prevQty - $qty, 2);
                    if ($newQty < 0) {
                        throw new InvalidArgumentException("Negative stock is not permitted for {$material->name}.");
                    }

                    $stock->quantity = $newQty;
                    $stock->save();

                    $this->createTransactionItem($tx->id, $materialId, $qty, (float) $material->standard_unit_cost, $prevQty, $newQty);
                }

                AuditLog::record(
                    'inventory.stock_issued',
                    $tx,
                    null,
                    [
                        'transaction_number' => $tx->transaction_number,
                        'source_warehouse_id' => $sourceWarehouse->id,
                        'site_id' => $site->id,
                        'work_order_id' => $workOrder?->id,
                        'items_count' => count($normalizedItems),
                    ],
                    "Issued stock ({$tx->transaction_number}) from {$sourceWarehouse->name} to {$site->name}"
                );

                return $tx;
            });
        } catch (Throwable $e) {
            $this->releaseSubmissionGuard($companyId, $submissionToken);
            throw $e;
        }
    }

    /**
     * Transfer stock atomically between two warehouses/site yards with deadlock-safe locking.
     */
    public function recordStockTransfer(array $data, array $items, User $actor): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);
        $submissionToken = isset($data['submission_token']) ? trim((string) $data['submission_token']) : null;

        $this->acquireSubmissionGuard($companyId, $submissionToken);

        try {
            return DB::transaction(function () use ($companyId, $token, $data, $items, $actor) {
                $sourceId = (int) ($data['source_warehouse_id'] ?? 0);
                $destId = (int) ($data['destination_warehouse_id'] ?? 0);

                if ($sourceId <= 0 || $destId <= 0 || $sourceId === $destId) {
                    throw new InvalidArgumentException('Source and destination storage locations must be two different active locations.');
                }

                $sourceWarehouse = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', $sourceId)
                    ->first();

                $destWarehouse = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', $destId)
                    ->first();

                if (! $sourceWarehouse || ! $sourceWarehouse->is_active || ! $destWarehouse || ! $destWarehouse->is_active) {
                    throw new InvalidArgumentException('Both source and destination storage locations must be active within your company.');
                }

                $reference = ! empty($data['challan_or_invoice_no']) ? trim((string) $data['challan_or_invoice_no']) : null;
                $this->assertUniqueMovementReference($companyId, 'inter_site_transfer', $reference);

                $normalizedItems = $this->normalizePositiveMovementItems($companyId, $items);

                $txNumber = $this->generateNextDocumentNumber(
                    $companyId,
                    'inter_site_transfer',
                    "TRF-{$token}",
                    fn (string $code) => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->where('transaction_number', $code)->exists(),
                    fn () => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->count()
                );

                $tx = InventoryTransaction::create([
                    'company_id' => $companyId,
                    'transaction_number' => $txNumber,
                    'transaction_type' => 'inter_site_transfer',
                    'source_warehouse_id' => $sourceWarehouse->id,
                    'destination_warehouse_id' => $destWarehouse->id,
                    'site_id' => $destWarehouse->site_id ?? $sourceWarehouse->site_id,
                    'challan_or_invoice_no' => $reference,
                    'transaction_date' => ! empty($data['transaction_date']) ? $data['transaction_date'] : Carbon::now()->format('Y-m-d'),
                    'performed_by' => $actor->id,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => Carbon::now(),
                    'status' => 'confirmed',
                    'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : "Transfer from {$sourceWarehouse->name} to {$destWarehouse->name}",
                ]);

                // Ensure destination InventoryStock rows exist before ordered locking
                foreach ($normalizedItems as $materialId => $_entry) {
                    InventoryStock::withoutGlobalScopes()->firstOrCreate(
                        [
                            'warehouse_id' => $destWarehouse->id,
                            'material_id' => $materialId,
                        ],
                        [
                            'company_id' => $companyId,
                            'quantity' => 0.00,
                            'reserved_quantity' => 0.00,
                        ]
                    );
                }

                $firstWhId = min($sourceWarehouse->id, $destWarehouse->id);
                $secondWhId = max($sourceWarehouse->id, $destWarehouse->id);

                foreach ($normalizedItems as $materialId => $entry) {
                    /** @var Material $material */
                    $material = $entry['material'];
                    $qty = $entry['quantity'];

                    $firstLock = InventoryStock::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('warehouse_id', $firstWhId)
                        ->where('material_id', $materialId)
                        ->lockForUpdate()
                        ->first();

                    $secondLock = InventoryStock::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('warehouse_id', $secondWhId)
                        ->where('material_id', $materialId)
                        ->lockForUpdate()
                        ->first();

                    $sourceStock = ($sourceWarehouse->id === $firstWhId) ? $firstLock : $secondLock;
                    $destStock = ($destWarehouse->id === $firstWhId) ? $firstLock : $secondLock;

                    $available = $sourceStock ? ((float) $sourceStock->quantity - (float) $sourceStock->reserved_quantity) : 0.0;
                    if (! $sourceStock || $available + 0.0001 < $qty) {
                        throw new InvalidArgumentException(
                            "Insufficient stock for {$material->name} in {$sourceWarehouse->name}. Available: ".number_format(max(0.0, $available), 2)." {$material->unit_of_measure}, transfer requested: ".number_format($qty, 2).'.'
                        );
                    }

                    $prevSourceQty = (float) $sourceStock->quantity;
                    $newSourceQty = round($prevSourceQty - $qty, 2);
                    if ($newSourceQty < 0) {
                        throw new InvalidArgumentException("Negative stock is not permitted for {$material->name}.");
                    }

                    $sourceStock->quantity = $newSourceQty;
                    $sourceStock->save();

                    $destStock->quantity = round(((float) $destStock->quantity) + $qty, 2);
                    $destStock->save();

                    $this->createTransactionItem($tx->id, $materialId, $qty, (float) $material->standard_unit_cost, $prevSourceQty, $newSourceQty);
                }

                AuditLog::record(
                    'inventory.stock_transferred',
                    $tx,
                    null,
                    [
                        'transaction_number' => $tx->transaction_number,
                        'source_warehouse_id' => $sourceWarehouse->id,
                        'destination_warehouse_id' => $destWarehouse->id,
                        'items_count' => count($normalizedItems),
                    ],
                    "Transferred stock ({$tx->transaction_number}) from {$sourceWarehouse->name} to {$destWarehouse->name}"
                );

                return $tx;
            });
        } catch (Throwable $e) {
            $this->releaseSubmissionGuard($companyId, $submissionToken);
            throw $e;
        }
    }

    /**
     * Return unused materials from a construction site back into an active warehouse/yard.
     */
    public function recordStockReturn(array $data, array $items, User $actor): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);
        $submissionToken = isset($data['submission_token']) ? trim((string) $data['submission_token']) : null;

        $this->acquireSubmissionGuard($companyId, $submissionToken);

        try {
            return DB::transaction(function () use ($companyId, $token, $data, $items, $actor) {
                $destWarehouse = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', (int) ($data['destination_warehouse_id'] ?? 0))
                    ->first();

                if (! $destWarehouse || ! $destWarehouse->is_active) {
                    throw new InvalidArgumentException('Selected destination storage location is invalid or inactive.');
                }

                $site = Site::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', (int) ($data['site_id'] ?? 0))
                    ->first();

                if (! $site) {
                    throw new InvalidArgumentException('Originating construction site is required for a site return.');
                }

                $workOrder = null;
                if (! empty($data['work_order_id'])) {
                    $workOrder = WorkOrder::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('id', (int) $data['work_order_id'])
                        ->first();

                    if (! $workOrder || ($workOrder->site_id && (int) $workOrder->site_id !== (int) $site->id)) {
                        throw new InvalidArgumentException('Selected work order does not belong to the returning site.');
                    }
                }

                $reference = ! empty($data['challan_or_invoice_no']) ? trim((string) $data['challan_or_invoice_no']) : null;
                $this->assertUniqueMovementReference($companyId, 'site_return', $reference);

                $normalizedItems = $this->normalizePositiveMovementItems($companyId, $items);

                $txNumber = $this->generateNextDocumentNumber(
                    $companyId,
                    'site_return',
                    "RET-{$token}",
                    fn (string $code) => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->where('transaction_number', $code)->exists(),
                    fn () => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->count()
                );

                $txPayload = [
                    'company_id' => $companyId,
                    'transaction_number' => $txNumber,
                    'transaction_type' => 'site_return',
                    'source_warehouse_id' => null,
                    'destination_warehouse_id' => $destWarehouse->id,
                    'site_id' => $site->id,
                    'challan_or_invoice_no' => $reference,
                    'transaction_date' => ! empty($data['transaction_date']) ? $data['transaction_date'] : Carbon::now()->format('Y-m-d'),
                    'performed_by' => $actor->id,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => Carbon::now(),
                    'status' => 'confirmed',
                    'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : "Returned unused materials from {$site->name}",
                ];

                if ($workOrder && Schema::hasColumn('inventory_transactions', 'work_order_id')) {
                    $txPayload['work_order_id'] = $workOrder->id;
                }

                $tx = InventoryTransaction::create($txPayload);

                foreach ($normalizedItems as $materialId => $entry) {
                    /** @var Material $material */
                    $material = $entry['material'];
                    $qty = $entry['quantity'];

                    $stock = InventoryStock::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('warehouse_id', $destWarehouse->id)
                        ->where('material_id', $materialId)
                        ->lockForUpdate()
                        ->first();

                    if (! $stock) {
                        $stock = InventoryStock::create([
                            'company_id' => $companyId,
                            'warehouse_id' => $destWarehouse->id,
                            'material_id' => $materialId,
                            'quantity' => 0.00,
                            'reserved_quantity' => 0.00,
                        ]);
                    }

                    $prevQty = (float) $stock->quantity;
                    $newQty = round($prevQty + $qty, 2);
                    $stock->quantity = $newQty;
                    $stock->save();

                    $this->createTransactionItem($tx->id, $materialId, $qty, (float) $material->standard_unit_cost, $prevQty, $newQty);
                }

                AuditLog::record(
                    'inventory.stock_returned',
                    $tx,
                    null,
                    [
                        'transaction_number' => $tx->transaction_number,
                        'site_id' => $site->id,
                        'destination_warehouse_id' => $destWarehouse->id,
                        'items_count' => count($normalizedItems),
                    ],
                    "Returned stock ({$tx->transaction_number}) from {$site->name} to {$destWarehouse->name}"
                );

                return $tx;
            });
        } catch (Throwable $e) {
            $this->releaseSubmissionGuard($companyId, $submissionToken);
            throw $e;
        }
    }

    /**
     * Submit a stock adjustment request (pending manager approval; stock is NOT modified until approved).
     */
    public function requestStockAdjustment(array $data, array $items, User $requester): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();
        $token = $this->resolveCompanyPrefixToken($companyId);
        $submissionToken = isset($data['submission_token']) ? trim((string) $data['submission_token']) : null;

        $reason = trim((string) ($data['adjustment_reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('A clear reason is mandatory for every stock adjustment.');
        }

        $this->acquireSubmissionGuard($companyId, $submissionToken);

        try {
            return DB::transaction(function () use ($companyId, $token, $data, $items, $requester, $reason) {
                $warehouse = Warehouse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('id', (int) ($data['warehouse_id'] ?? 0))
                    ->first();

                if (! $warehouse || ! $warehouse->is_active) {
                    throw new InvalidArgumentException('Selected storage location is invalid or inactive.');
                }

                $reference = ! empty($data['challan_or_invoice_no']) ? trim((string) $data['challan_or_invoice_no']) : null;
                $this->assertUniqueMovementReference($companyId, 'stock_adjustment', $reference);

                $normalizedDeltas = $this->normalizeSignedAdjustmentItems($companyId, $items);

                // Pre-check current stock so negative-resulting adjustments are rejected immediately
                foreach ($normalizedDeltas as $materialId => $entry) {
                    /** @var Material $material */
                    $material = $entry['material'];
                    $delta = $entry['delta'];

                    if ($delta < 0) {
                        $stock = InventoryStock::withoutGlobalScopes()
                            ->where('company_id', $companyId)
                            ->where('warehouse_id', $warehouse->id)
                            ->where('material_id', $materialId)
                            ->lockForUpdate()
                            ->first();

                        $currentQty = $stock ? (float) $stock->quantity : 0.0;
                        if ($currentQty + $delta < -0.0001) {
                            throw new InvalidArgumentException(
                                "Adjustment ({$delta}) would result in negative stock for {$material->name} (current on hand: ".number_format($currentQty, 2)." {$material->unit_of_measure})."
                            );
                        }
                    }
                }

                $txNumber = $this->generateNextDocumentNumber(
                    $companyId,
                    'stock_adjustment',
                    "ADJ-{$token}",
                    fn (string $code) => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->where('transaction_number', $code)->exists(),
                    fn () => InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->count()
                );

                $notesText = ! empty($data['notes']) ? trim((string) $data['notes']) : null;
                $combinedNotes = $notesText ? "{$reason} — {$notesText}" : $reason;

                $txPayload = [
                    'company_id' => $companyId,
                    'transaction_number' => $txNumber,
                    'transaction_type' => 'stock_adjustment',
                    'source_warehouse_id' => $warehouse->id,
                    'destination_warehouse_id' => $warehouse->id,
                    'site_id' => $warehouse->site_id,
                    'challan_or_invoice_no' => $reference,
                    'transaction_date' => ! empty($data['transaction_date']) ? $data['transaction_date'] : Carbon::now()->format('Y-m-d'),
                    'performed_by' => $requester->id,
                    'confirmed_by' => null,
                    'confirmed_at' => null,
                    'status' => 'pending_confirmation',
                    'notes' => $combinedNotes,
                ];

                if (Schema::hasColumn('inventory_transactions', 'adjustment_reason')) {
                    $txPayload['adjustment_reason'] = $reason;
                }

                $tx = InventoryTransaction::create($txPayload);

                foreach ($normalizedDeltas as $materialId => $entry) {
                    /** @var Material $material */
                    $material = $entry['material'];
                    $delta = $entry['delta'];

                    $currentQty = (float) (InventoryStock::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('material_id', $materialId)
                        ->value('quantity') ?? 0.0);

                    $this->createTransactionItem(
                        $tx->id,
                        $materialId,
                        $delta,
                        (float) $material->standard_unit_cost,
                        $currentQty,
                        round($currentQty + $delta, 2)
                    );
                }

                AuditLog::record(
                    'inventory.adjustment_requested',
                    $tx,
                    null,
                    [
                        'transaction_number' => $tx->transaction_number,
                        'warehouse_id' => $warehouse->id,
                        'adjustment_reason' => $reason,
                        'status' => 'pending_confirmation',
                    ],
                    "Requested stock adjustment ({$tx->transaction_number}) for {$warehouse->name}: {$reason}"
                );

                return $tx;
            });
        } catch (Throwable $e) {
            $this->releaseSubmissionGuard($companyId, $submissionToken);
            throw $e;
        }
    }

    /**
     * Approve a pending stock adjustment (enforces maker-checker separation of duties and applies stock changes atomically).
     */
    public function approveStockAdjustment(InventoryTransaction $tx, User $approver): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();

        return DB::transaction(function () use ($companyId, $tx, $approver) {
            $lockedTx = InventoryTransaction::withoutGlobalScopes()
                ->with('items.material')
                ->where('company_id', $companyId)
                ->where('id', $tx->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTx->transaction_type !== 'stock_adjustment' || $lockedTx->status !== 'pending_confirmation') {
                throw new InvalidArgumentException('Only pending stock adjustments can be approved.');
            }

            if ((int) $lockedTx->performed_by === (int) $approver->id) {
                throw new InvalidArgumentException('Requesters cannot approve their own stock adjustment.');
            }

            $warehouseId = (int) ($lockedTx->destination_warehouse_id ?: $lockedTx->source_warehouse_id);
            $sortedItems = $lockedTx->items->sortBy('material_id');

            foreach ($sortedItems as $item) {
                $materialId = (int) $item->material_id;
                $delta = (float) $item->quantity;

                $stock = InventoryStock::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('material_id', $materialId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    $stock = InventoryStock::create([
                        'company_id' => $companyId,
                        'warehouse_id' => $warehouseId,
                        'material_id' => $materialId,
                        'quantity' => 0.00,
                        'reserved_quantity' => 0.00,
                    ]);
                }

                $prevQty = (float) $stock->quantity;
                $newQty = round($prevQty + $delta, 2);

                if ($newQty < -0.0001) {
                    $matName = $item->material?->name ?? "Material #{$materialId}";
                    throw new InvalidArgumentException(
                        "Cannot approve adjustment: {$matName} stock would become negative (current: ".number_format($prevQty, 2).', adjustment: '.number_format($delta, 2).').'
                    );
                }

                $stock->quantity = max(0.0, $newQty);
                $stock->save();

                if (Schema::hasColumn('inventory_transaction_items', 'previous_quantity')) {
                    $item->previous_quantity = $prevQty;
                    $item->resulting_quantity = $stock->quantity;
                    $item->save();
                }
            }

            $lockedTx->status = 'confirmed';
            $lockedTx->confirmed_by = $approver->id;
            $lockedTx->confirmed_at = Carbon::now();
            $lockedTx->save();

            AuditLog::record(
                'inventory.adjustment_approved',
                $lockedTx,
                ['status' => 'pending_confirmation'],
                [
                    'status' => 'confirmed',
                    'confirmed_by' => $approver->id,
                    'transaction_number' => $lockedTx->transaction_number,
                ],
                "Approved stock adjustment {$lockedTx->transaction_number}"
            );

            return $lockedTx;
        });
    }

    /**
     * Reject a pending stock adjustment without modifying stock balances.
     */
    public function rejectStockAdjustment(InventoryTransaction $tx, User $reviewer, string $reason): InventoryTransaction
    {
        $companyId = (int) TenantContext::getCompanyId();
        $cleanReason = trim($reason);

        if ($cleanReason === '') {
            throw new InvalidArgumentException('A rejection reason is required when rejecting a stock adjustment.');
        }

        return DB::transaction(function () use ($companyId, $tx, $reviewer, $cleanReason) {
            $lockedTx = InventoryTransaction::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('id', $tx->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTx->transaction_type !== 'stock_adjustment' || $lockedTx->status !== 'pending_confirmation') {
                throw new InvalidArgumentException('Only pending stock adjustments can be rejected.');
            }

            if ((int) $lockedTx->performed_by === (int) $reviewer->id) {
                throw new InvalidArgumentException('Requesters cannot review their own stock adjustment.');
            }

            $lockedTx->status = 'rejected';
            $lockedTx->confirmed_by = $reviewer->id;
            $lockedTx->confirmed_at = Carbon::now();
            if (Schema::hasColumn('inventory_transactions', 'rejection_reason')) {
                $lockedTx->rejection_reason = $cleanReason;
            } else {
                $lockedTx->notes = trim(($lockedTx->notes ? $lockedTx->notes.' | ' : '')."Rejected: {$cleanReason}");
            }
            $lockedTx->save();

            AuditLog::record(
                'inventory.adjustment_rejected',
                $lockedTx,
                ['status' => 'pending_confirmation'],
                [
                    'status' => 'rejected',
                    'confirmed_by' => $reviewer->id,
                    'rejection_reason' => $cleanReason,
                ],
                "Rejected stock adjustment {$lockedTx->transaction_number}: {$cleanReason}"
            );

            return $lockedTx;
        });
    }

    /**
     * Resolve dynamic short company token for document prefixes (e.g. 'NSD', 'NSD-P4' -> 'NSD'; 'ALPHA' -> 'ALPHA').
     */
    public function resolveCompanyPrefixToken(int $companyId): string
    {
        $rawCode = Company::withoutGlobalScopes()->where('id', $companyId)->value('code');
        $firstSegment = explode('-', strtoupper(trim((string) $rawCode)))[0] ?? '';
        $clean = preg_replace('/[^A-Z0-9]/', '', $firstSegment) ?? '';

        return $clean !== '' ? substr($clean, 0, 10) : 'ORG';
    }

    /**
     * Normalize and validate positive movement items sorted by material_id ASC for deadlock-free locking.
     *
     * @return array<int, array{material: Material, quantity: float}>
     */
    private function normalizePositiveMovementItems(int $companyId, array $items): array
    {
        $aggregated = [];

        foreach ($items as $item) {
            $materialId = (int) ($item['material_id'] ?? 0);
            $qty = round((float) ($item['quantity'] ?? 0), 2);

            if ($materialId <= 0 && $qty == 0.0) {
                continue;
            }

            if ($materialId <= 0 || $qty <= 0) {
                throw new InvalidArgumentException('Each stock movement line requires a valid material and a quantity greater than zero.');
            }

            $aggregated[$materialId] = round(($aggregated[$materialId] ?? 0.0) + $qty, 2);
        }

        if (empty($aggregated)) {
            throw new InvalidArgumentException('At least one material item with quantity greater than zero is required.');
        }

        ksort($aggregated);

        $materials = Material::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', array_keys($aggregated))
            ->get()
            ->keyBy('id');

        if ($materials->count() !== count($aggregated)) {
            throw new InvalidArgumentException('One or more selected materials are invalid, inactive, or belong to another company.');
        }

        $result = [];
        foreach ($aggregated as $matId => $qty) {
            $result[$matId] = [
                'material' => $materials->get($matId),
                'quantity' => $qty,
            ];
        }

        return $result;
    }

    /**
     * Normalize and validate signed delta items for stock adjustments sorted by material_id ASC.
     *
     * @return array<int, array{material: Material, delta: float}>
     */
    private function normalizeSignedAdjustmentItems(int $companyId, array $items): array
    {
        $aggregated = [];

        foreach ($items as $item) {
            $materialId = (int) ($item['material_id'] ?? 0);
            $delta = round((float) ($item['quantity_delta'] ?? $item['quantity'] ?? 0), 2);

            if ($materialId <= 0 && $delta == 0.0) {
                continue;
            }

            if ($materialId <= 0 || abs($delta) < 0.01) {
                throw new InvalidArgumentException('Each stock adjustment line requires a valid material and a non-zero quantity adjustment.');
            }

            $aggregated[$materialId] = round(($aggregated[$materialId] ?? 0.0) + $delta, 2);
        }

        // Remove any line whose aggregated delta cancelled out to 0
        $aggregated = array_filter($aggregated, fn (float $d) => abs($d) >= 0.01);

        if (empty($aggregated)) {
            throw new InvalidArgumentException('At least one material item with a non-zero adjustment quantity is required.');
        }

        ksort($aggregated);

        $materials = Material::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', array_keys($aggregated))
            ->get()
            ->keyBy('id');

        if ($materials->count() !== count($aggregated)) {
            throw new InvalidArgumentException('One or more selected materials are invalid or belong to another company.');
        }

        $result = [];
        foreach ($aggregated as $matId => $delta) {
            $result[$matId] = [
                'material' => $materials->get($matId),
                'delta' => $delta,
            ];
        }

        return $result;
    }

    private function assertUniqueMovementReference(int $companyId, string $type, ?string $reference): void
    {
        if (! $reference) {
            return;
        }

        $exists = InventoryTransaction::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('transaction_type', $type)
            ->where('challan_or_invoice_no', $reference)
            ->where('status', '!=', 'rejected')
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("Reference slip '{$reference}' has already been recorded for this stock movement type.");
        }
    }

    private function acquireSubmissionGuard(int $companyId, ?string $submissionToken): void
    {
        if (! $submissionToken) {
            return;
        }

        $cacheKey = "inv_submission_guard:{$companyId}:".sha1($submissionToken);
        if (! Cache::add($cacheKey, true, now()->addMinutes(10))) {
            throw new InvalidArgumentException('Duplicate stock movement submission detected. This transaction has already been processed.');
        }
    }

    private function releaseSubmissionGuard(int $companyId, ?string $submissionToken): void
    {
        if (! $submissionToken) {
            return;
        }

        $cacheKey = "inv_submission_guard:{$companyId}:".sha1($submissionToken);
        Cache::forget($cacheKey);
    }

    private function createTransactionItem(
        int $transactionId,
        int $materialId,
        float $quantity,
        float $unitCost,
        ?float $prevQty = null,
        ?float $resultingQty = null
    ): InventoryTransactionItem {
        $payload = [
            'inventory_transaction_id' => $transactionId,
            'material_id' => $materialId,
            'quantity' => $quantity,
            'unit_cost' => max(0.0, $unitCost),
        ];

        if ($prevQty !== null && Schema::hasColumn('inventory_transaction_items', 'previous_quantity')) {
            $payload['previous_quantity'] = $prevQty;
            $payload['resulting_quantity'] = $resultingQty;
        }

        return InventoryTransactionItem::create($payload);
    }

    /**
     * Generates a concurrency-safe sequential document number inside an active transaction.
     */
    private function generateNextDocumentNumber(
        int $companyId,
        string $sequenceType,
        string $prefix,
        callable $existsCallback,
        callable $countCallback
    ): string {
        $year = Carbon::now()->format('Y');
        $nextNum = ((int) $countCallback()) + 1;

        if (Schema::hasTable('worker_sequences')) {
            $seq = DB::table('worker_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->where('year', (int) $year)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                DB::table('worker_sequences')->insert([
                    'company_id' => $companyId,
                    'prefix' => $prefix,
                    'year' => (int) $year,
                    'last_number' => $nextNum,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $nextNum = max($nextNum, ((int) $seq->last_number) + 1);
                DB::table('worker_sequences')
                    ->where('id', $seq->id)
                    ->update(['last_number' => $nextNum, 'updated_at' => now()]);
            }
        }

        $code = sprintf('%s-%s-%03d', $prefix, $year, $nextNum);
        while ($existsCallback($code)) {
            $nextNum++;
            $code = sprintf('%s-%s-%03d', $prefix, $year, $nextNum);
        }

        return $code;
    }
}
