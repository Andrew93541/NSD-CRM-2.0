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
        $companyId = TenantContext::getCompanyId();
        $year = Carbon::now()->format('Y');
        $count = PurchaseRequest::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
        $reqNumber = sprintf('PR-NSD-%s-%03d', $year, $count);

        if (!empty($data['is_emergency']) && empty($data['emergency_justification'])) {
            throw new InvalidArgumentException('Emergency purchase justification is mandatory for emergency requests.');
        }

        return DB::transaction(function () use ($companyId, $data, $items, $requester, $reqNumber) {
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
                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'material_id' => $item['material_id'],
                    'quantity_requested' => $item['quantity'],
                    'estimated_unit_cost' => $item['estimated_unit_cost'] ?? 0.00,
                    'purpose_notes' => $item['purpose_notes'] ?? null,
                ]);
            }

            return $pr;
        });
    }

    public function approvePurchaseRequest(PurchaseRequest $pr, User $approver, ?string $comments = null): PurchaseRequest
    {
        if ($pr->status !== 'pending') {
            throw new InvalidArgumentException('Purchase request is not pending approval.');
        }

        $pr->status = 'approved';
        $pr->approved_by = $approver->id;
        $pr->approved_at = Carbon::now();
        $pr->review_comments = $comments;
        $pr->save();

        return $pr;
    }

    public function createPurchaseOrder(PurchaseRequest $pr, array $data, User $creator): PurchaseOrder
    {
        if ($pr->status !== 'approved') {
            throw new InvalidArgumentException('Purchase request must be approved before issuing purchase order.');
        }

        $companyId = TenantContext::getCompanyId();
        $year = Carbon::now()->format('Y');
        $count = PurchaseOrder::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
        $poNumber = sprintf('PO-NSD-%s-%03d', $year, $count);

        return DB::transaction(function () use ($companyId, $pr, $data, $creator, $poNumber) {
            $totalAmount = 0.00;
            $itemsData = [];

            foreach ($pr->items as $reqItem) {
                $qty = $reqItem->quantity_requested;
                $unitPrice = $data['unit_prices'][$reqItem->material_id] ?? $reqItem->estimated_unit_cost;
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
                'purchase_request_id' => $pr->id,
                'site_id' => $pr->site_id,
                'warehouse_id' => $pr->warehouse_id,
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

            $pr->status = 'ordered';
            $pr->save();

            return $po;
        });
    }

    public function recordGoodsReceipt(PurchaseOrder $po, int $destinationWarehouseId, array $receivedQuantities, User $receiver, ?string $challan = null): InventoryTransaction
    {
        $companyId = TenantContext::getCompanyId();

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

            $year = Carbon::now()->format('Y');
            $count = InventoryTransaction::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
            $txNumber = sprintf('GRN-NSD-%s-%03d', $year, $count);

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
                if ($recQty > 0) {
                    $remaining = max(0.00, (float) $poItem->quantity_ordered - (float) $poItem->quantity_received);
                    if ($recQty > $remaining) {
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
}
