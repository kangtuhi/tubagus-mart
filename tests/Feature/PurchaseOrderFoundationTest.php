<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('purchase order belongs to supplier and contains products', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'number' => 'PO-20260829-0001',
        'ordered_at' => '2026-08-29',
        'status' => PurchaseOrderStatus::DRAFT,
    ]);

    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 5000,
        'line_total' => 50000,
    ]);

    expect($purchaseOrder->supplier->is($supplier))->toBeTrue()
        ->and($purchaseOrder->items)->toHaveCount(1)
        ->and($item->product->is($product))->toBeTrue()
        ->and($purchaseOrder->status)->toBe(PurchaseOrderStatus::DRAFT);
});

test('purchase order status is cast to enum', function () {
    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'PO-20260829-0002',
        'ordered_at' => '2026-08-29',
    ]);

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::DRAFT);
});
