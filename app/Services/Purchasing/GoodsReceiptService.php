<?php

namespace App\Services\Purchasing;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\InventoryLocation;
use App\Models\PurchaseOrder;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Database\DatabaseManager;

class GoodsReceiptService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly InventoryService $inventory,
    ) {}

    public function receive(
        PurchaseOrder $purchaseOrder,
        InventoryLocation $location,
        string $number,
        array $items,
        ?int $receivedBy = null,
        ?string $notes = null,
    ): GoodsReceipt {
        return $this->database->transaction(function () use ($purchaseOrder, $location, $number, $items, $receivedBy, $notes) {
            $purchaseOrder = PurchaseOrder::query()
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! in_array($purchaseOrder->status, [
                PurchaseOrderStatus::APPROVED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ], true)) {
                throw new DomainException('Only approved or partially received purchase orders can receive goods.');
            }

            if ($items === []) {
                throw new DomainException('A goods receipt must contain at least one item.');
            }

            $orderItems = $purchaseOrder->items->keyBy('id');

            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'location_id' => $location->id,
                'number' => $number,
                'received_at' => now(),
                'received_by' => $receivedBy,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $purchaseOrderItem = $orderItems->get($item['purchase_order_item_id'] ?? null);
                $quantity = (float) ($item['quantity'] ?? 0);

                if (! $purchaseOrderItem) {
                    throw new DomainException('Goods receipt item does not belong to the purchase order.');
                }

                if ($quantity <= 0) {
                    throw new DomainException('Received quantity must be greater than zero.');
                }

                $remaining = (float) $purchaseOrderItem->quantity - (float) $purchaseOrderItem->received_quantity;

                if ($quantity > $remaining) {
                    throw new DomainException('Received quantity exceeds the remaining purchase order quantity.');
                }

                $unitCost = (float) $purchaseOrderItem->unit_cost;

                $receipt->items()->create([
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ]);

                $purchaseOrderItem->increment('received_quantity', $quantity);

                $this->inventory->recordMovement(
                    $purchaseOrderItem->product,
                    $location->id,
                    InventoryMovementType::PURCHASE,
                    $quantity,
                    $unitCost,
                    "Goods receipt {$number}",
                    $receivedBy,
                );
            }

            $purchaseOrder->load('items');
            $fullyReceived = $purchaseOrder->items->every(
                fn ($item) => (float) $item->received_quantity >= (float) $item->quantity
            );

            $purchaseOrder->update([
                'status' => $fullyReceived
                    ? PurchaseOrderStatus::RECEIVED
                    : PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ]);

            return $receipt->load('items');
        });
    }
}
