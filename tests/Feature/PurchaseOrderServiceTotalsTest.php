<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('submitting a purchase order recalculates item and order totals', function () {
    $supplier = Supplier::factory()->create();
    $order = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'number' => 'PO-TOTAL-001',
        'ordered_at' => '2026-08-29',
        'status' => PurchaseOrderStatus::DRAFT,
        'subtotal' => 999999,
        'discount_amount' => 999999,
        'tax_amount' => 999999,
        'grand_total' => 999999,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 10,
        'unit_cost' => 1250,
        'discount_amount' => 500,
        'tax_amount' => 100,
        'line_total' => 999999,
    ]);

    $result = app(PurchaseOrderService::class)->submit($order);
    $item = $result->items()->first();

    expect($item->line_total)->toBe('12100.00')
        ->and($result->subtotal)->toBe('12500.00')
        ->and($result->discount_amount)->toBe('500.00')
        ->and($result->tax_amount)->toBe('100.00')
        ->and($result->grand_total)->toBe('12100.00');
});

test('approval recalculates totals before locking the purchase order', function () {
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'PO-TOTAL-002',
        'ordered_at' => '2026-08-29',
        'status' => PurchaseOrderStatus::SUBMITTED,
        'grand_total' => 1,
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 2,
        'unit_cost' => 5000,
        'discount_amount' => 0,
        'tax_amount' => 500,
        'line_total' => 1,
    ]);

    $result = app(PurchaseOrderService::class)->approve($order->refresh());

    expect($result->grand_total)->toBe('10500.00')
        ->and($result->status)->toBe(PurchaseOrderStatus::APPROVED);
});
