<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function purchaseOrderForService(?Supplier $supplier = null): PurchaseOrder
{
    $supplier ??= Supplier::factory()->create();

    return PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'number' => 'PO-'.fake()->unique()->numerify('######'),
        'ordered_at' => '2026-08-29',
        'status' => PurchaseOrderStatus::DRAFT,
    ]);
}

function addPurchaseOrderItem(PurchaseOrder $purchaseOrder): void
{
    PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 5,
        'unit_cost' => 1000,
        'line_total' => 5000,
    ]);
}

test('draft purchase order with items can be submitted', function () {
    $purchaseOrder = purchaseOrderForService();
    addPurchaseOrderItem($purchaseOrder);

    $result = app(PurchaseOrderService::class)->submit($purchaseOrder);

    expect($result->status)->toBe(PurchaseOrderStatus::SUBMITTED);
});

test('empty purchase order cannot be submitted', function () {
    $purchaseOrder = purchaseOrderForService();

    expect(fn () => app(PurchaseOrderService::class)->submit($purchaseOrder))
        ->toThrow(DomainException::class, 'A purchase order must contain at least one item.');
});

test('submitted purchase order can be approved by an authenticated user', function () {
    $user = User::factory()->create();
    $purchaseOrder = purchaseOrderForService();
    addPurchaseOrderItem($purchaseOrder);
    app(PurchaseOrderService::class)->submit($purchaseOrder);

    $this->actingAs($user);
    $result = app(PurchaseOrderService::class)->approve($purchaseOrder);

    expect($result->status)->toBe(PurchaseOrderStatus::APPROVED)
        ->and($result->approved_by)->toBe($user->id)
        ->and($result->approved_at)->not->toBeNull();
});

test('inactive supplier purchase order cannot be approved', function () {
    $supplier = Supplier::factory()->inactive()->create();
    $purchaseOrder = purchaseOrderForService($supplier);
    addPurchaseOrderItem($purchaseOrder);
    app(PurchaseOrderService::class)->submit($purchaseOrder);
    $this->actingAs(User::factory()->create());

    expect(fn () => app(PurchaseOrderService::class)->approve($purchaseOrder))
        ->toThrow(DomainException::class, 'Inactive suppliers cannot have purchase orders approved.');
});

test('approved purchase order can be cancelled', function () {
    $purchaseOrder = purchaseOrderForService();
    addPurchaseOrderItem($purchaseOrder);
    app(PurchaseOrderService::class)->submit($purchaseOrder);
    $this->actingAs(User::factory()->create());
    app(PurchaseOrderService::class)->approve($purchaseOrder);

    $result = app(PurchaseOrderService::class)->cancel($purchaseOrder);

    expect($result->status)->toBe(PurchaseOrderStatus::CANCELLED);
});

test('received purchase order cannot be cancelled', function () {
    $purchaseOrder = purchaseOrderForService();
    $purchaseOrder->update(['status' => PurchaseOrderStatus::RECEIVED]);

    expect(fn () => app(PurchaseOrderService::class)->cancel($purchaseOrder))
        ->toThrow(DomainException::class, 'This purchase order cannot be cancelled in its current status.');
});
