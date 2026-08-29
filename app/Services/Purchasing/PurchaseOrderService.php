<?php

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::SUBMITTED);
    }

    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->database->transaction(function () use ($purchaseOrder) {
            $purchaseOrder->refresh();

            if ($purchaseOrder->status !== PurchaseOrderStatus::SUBMITTED) {
                throw new DomainException('Only submitted purchase orders can be approved.');
            }

            if ($purchaseOrder->items()->doesntExist()) {
                throw new DomainException('A purchase order must contain at least one item.');
            }

            if (! $purchaseOrder->supplier->is_active) {
                throw new DomainException('Inactive suppliers cannot have purchase orders approved.');
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $purchaseOrder->refresh();
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->database->transaction(function () use ($purchaseOrder) {
            $purchaseOrder->refresh();

            if (! in_array($purchaseOrder->status, [
                PurchaseOrderStatus::DRAFT,
                PurchaseOrderStatus::SUBMITTED,
                PurchaseOrderStatus::APPROVED,
            ], true)) {
                throw new DomainException('This purchase order cannot be cancelled in its current status.');
            }

            $purchaseOrder->update(['status' => PurchaseOrderStatus::CANCELLED]);

            return $purchaseOrder->refresh();
        });
    }

    private function transition(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $status): PurchaseOrder
    {
        return $this->database->transaction(function () use ($purchaseOrder, $status) {
            $purchaseOrder->refresh();

            if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT) {
                throw new DomainException('Only draft purchase orders can be submitted.');
            }

            if ($purchaseOrder->items()->doesntExist()) {
                throw new DomainException('A purchase order must contain at least one item.');
            }

            $purchaseOrder->update(['status' => $status]);

            return $purchaseOrder->refresh();
        });
    }
}
