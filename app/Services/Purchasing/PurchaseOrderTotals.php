<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use DomainException;

class PurchaseOrderTotals
{
    public function calculateItem(PurchaseOrderItem $item): array
    {
        $quantity = (float) $item->quantity;
        $unitCost = (float) $item->unit_cost;
        $discount = (float) $item->discount_amount;
        $tax = (float) $item->tax_amount;

        if ($quantity <= 0) {
            throw new DomainException('Purchase quantity must be greater than zero.');
        }

        if ($unitCost < 0) {
            throw new DomainException('Unit cost cannot be negative.');
        }

        if ($discount < 0 || $tax < 0) {
            throw new DomainException('Discount and tax cannot be negative.');
        }

        $gross = round($quantity * $unitCost, 2);
        $net = round($gross - $discount, 2);

        if ($net < 0) {
            throw new DomainException('Discount cannot exceed gross line amount.');
        }

        return [
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'line_total' => round($net + $tax, 2),
        ];
    }

    public function calculateOrder(PurchaseOrder $purchaseOrder): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($purchaseOrder->items as $item) {
            $totals = $this->calculateItem($item);
            $subtotal += round(((float) $item->quantity * (float) $item->unit_cost), 2);
            $discount += $totals['discount_amount'];
            $tax += $totals['tax_amount'];
        }

        $subtotal = round($subtotal, 2);
        $discount = round($discount, 2);
        $tax = round($tax, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'grand_total' => round($subtotal - $discount + $tax, 2),
        ];
    }
}
