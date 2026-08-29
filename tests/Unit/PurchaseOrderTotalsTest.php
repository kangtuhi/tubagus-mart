<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Purchasing\PurchaseOrderTotals;
use DomainException;

function purchaseItemForTotals(array $attributes = []): PurchaseOrderItem
{
    return new PurchaseOrderItem(array_merge([
        'quantity' => 10,
        'unit_cost' => 1250,
        'discount_amount' => 500,
        'tax_amount' => 100,
    ], $attributes));
}

test('purchase order item totals are calculated correctly', function () {
    $totals = app(PurchaseOrderTotals::class)->calculateItem(purchaseItemForTotals());

    expect($totals)->toBe([
        'discount_amount' => 500.0,
        'tax_amount' => 100.0,
        'line_total' => 12100.0,
    ]);
});

test('purchase order totals aggregate all items', function () {
    $order = new PurchaseOrder;
    $order->setRelation('items', collect([
        purchaseItemForTotals(['quantity' => 10, 'unit_cost' => 1250, 'discount_amount' => 500, 'tax_amount' => 100]),
        purchaseItemForTotals(['quantity' => 4, 'unit_cost' => 2000, 'discount_amount' => 200, 'tax_amount' => 300]),
    ]));

    expect(app(PurchaseOrderTotals::class)->calculateOrder($order))->toBe([
        'subtotal' => 20500.0,
        'discount_amount' => 700.0,
        'tax_amount' => 400.0,
        'grand_total' => 20200.0,
    ]);
});

test('purchase quantity must be positive', function () {
    expect(fn () => app(PurchaseOrderTotals::class)->calculateItem(purchaseItemForTotals(['quantity' => 0])))
        ->toThrow(DomainException::class, 'Purchase quantity must be greater than zero.');
});

test('unit cost cannot be negative', function () {
    expect(fn () => app(PurchaseOrderTotals::class)->calculateItem(purchaseItemForTotals(['unit_cost' => -1])))
        ->toThrow(DomainException::class, 'Unit cost cannot be negative.');
});

test('discount cannot exceed gross line amount', function () {
    expect(fn () => app(PurchaseOrderTotals::class)->calculateItem(purchaseItemForTotals([
        'quantity' => 2,
        'unit_cost' => 100,
        'discount_amount' => 201,
    ])))->toThrow(DomainException::class, 'Discount cannot exceed gross line amount.');
});
