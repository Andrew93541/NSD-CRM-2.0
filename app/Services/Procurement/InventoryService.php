<?php

namespace App\Services\Procurement;

use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function createPurchaseRequest(array $data, array $items, User $requester): PurchaseRequest
    {
        $companyId = (int) TenantContext::getCompanyId();

        if (!empty($data['is_emergency']) && empty($data['emergency_justification'])) {
            throw new InvalidArgumentException('Emergency purchase justification is mandatory for emergency requests.');
        }

        return DB::transaction(function () use ($companyId, $data, $items, $requester) {
            $reqNumber = $this->generateNextDocumentNumber(
                $companyId,
                'purchase_request',
                'PR-NSD',
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
                'is_emergency' => !empty($data['is_emergency']),
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

    public function approvePurchaseRequest(PurchaseRequest $pr, User $approver, ?string $comments = null): PurchaseRequest
    {
        return DB::transaction(function () use ($pr, $approver, $comments) {
            $lockedPr = PurchaseRequest::withoutGlobalScopes()
                ->where('id', $pr->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPr->status !== 'pending') {
                throw new InvalidArgumentException('Purchase request is not pending approval.');
            }

            $lockedPr->status = 'approved';
            $lockedPr->approved_by = $approver->id;
            $lockedPr->approved_at = Carbon::now();
            $lockedPr->review_comments = $comments;
            $lockedPr->save();

            return $lockedPr;
        });
    }

    public function createPurchaseOrder(PurchaseRequest $pr, array $data, User $creator): PurchaseOrder
    {
        $companyId = (int) TenantContext::getCompanyId();

        return DB::transaction(function () use ($companyId, $pr, $data, $creator) {
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
                'PO-NSD',
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

            $po = PurchaseOrder::create([
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
            ]);

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

        return DB::transaction(function () use ($companyId, $po, $destinationWarehouseId, $receivedQuantities, $receiver, $challan) {
            // Pessimistic lock on purchase order to prevent concurrent race conditions
            $lockedPo = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedPo->status, ['issued', 'partially_received'], true)) {
                throw new InvalidArgumentException('Purchase order is already fully received or invalid for receipt.');
            }

            // Idempotency safeguard: check if duplicate challan already processed
            if (!empty($challan)) {
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
                'GRN-NSD',
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
                'notes' => 'Goods receipt confirmed from PO ' . $lockedPo->po_number,
            ]);

            $allCompleted = true;
            $anyReceived = false;

            // Load items with lock
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

                    InventoryTransactionItem::create([
                        'inventory_transaction_id' => $tx->id,
                        'material_id' => $poItem->material_id,
                        'quantity' => $recQty,
                        'unit_cost' => $poItem->unit_price,
                    ]);

                    // Update PO Item
                    $poItem->quantity_received += $recQty;
                    $poItem->save();
                    $anyReceived = true;

                    // Increment InventoryStock with locking
                    $stock = InventoryStock::where('company_id', $companyId)
                        ->where('warehouse_id', $destinationWarehouseId)
                        ->where('material_id', $poItem->material_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        $stock = InventoryStock::create([
                            'company_id' => $companyId,
                            'warehouse_id' => $destinationWarehouseId,
                            'material_id' => $poItem->material_id,
                            'quantity' => 0.00,
                            'reserved_quantity' => 0.00,
                        ]);
                    }

                    $stock->quantity += $recQty;
                    $stock->save();
                }

                if ($poItem->quantity_received < $poItem->quantity_ordered) {
                    $allCompleted = false;
                }
            }

            if (!$anyReceived) {
                throw new InvalidArgumentException('At least one item must have a quantity greater than zero to confirm goods receipt.');
            }

            $lockedPo->status = $allCompleted ? 'received' : 'partially_received';
            $lockedPo->save();

            return $tx;
        });
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

        if (\Illuminate\Support\Facades\Schema::hasTable('worker_sequences')) {
            $seq = DB::table('worker_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->where('year', (int) $year)
                ->lockForUpdate()
                ->first();

            if (!$seq) {
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
