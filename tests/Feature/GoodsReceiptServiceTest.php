<?php

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\GoodsReceiptService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function approvedPurchaseOrderForReceipt(): array
{
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();
    $order = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'number' => 'PO-'.fake()->unique()->numerify('######'),
        'ordered_at' => '2026-08-29',
        'status' => PurchaseOrderStatus::APPROVED,
    ]);
    $item = PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 5000,
        'line_total' => 50000,
    ]);

    return [$order, $item, $product, $location];
}

test('partial receipt updates purchase order and inventory', function () {
    [$order, $item, $product, $location] = approvedPurchaseOrderForReceipt();
    $user = User::factory()->create();

    $receipt = app(GoodsReceiptService::class)->receive(
        $order,
        $location,
        'GR-20260829-0001',
        [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
        $user->id,
    );

    $item->refresh();
    $order->refresh();
    $stock = InventoryStock::query()->where('product_id', $product->id)->where('location_id', $location->id)->first();

    expect($receipt)->toBeInstanceOf(GoodsReceipt::class)
        ->and($item->received_quantity)->toBe('4.000')
        ->and($order->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED)
        ->and($stock->quantity)->toBe('4.000')
        ->and(InventoryMovement::where('movement_type', InventoryMovementType::PURCHASE)->count())->toBe(1);
});

test('final receipt marks purchase order received', function () {
    [$order, $item, $product, $location] = approvedPurchaseOrderForReceipt();
    $service = app(GoodsReceiptService::class);

    $service->receive($order, $location, 'GR-1', [['purchase_order_item_id' => $item->id, 'quantity' => 6]]);
    $service->receive($order, $location, 'GR-2', [['purchase_order_item_id' => $item->id, 'quantity' => 4]]);

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::RECEIVED)
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity)->toBe('10.000')
        ->and(InventoryMovement::count())->toBe(2);
});

test('receipt cannot exceed remaining purchase order quantity', function () {
    [$order, $item, $product, $location] = approvedPurchaseOrderForReceipt();
    $service = app(GoodsReceiptService::class);

    $service->receive($order, $location, 'GR-1', [['purchase_order_item_id' => $item->id, 'quantity' => 8]]);

    expect(fn () => $service->receive($order, $location, 'GR-2', [['purchase_order_item_id' => $item->id, 'quantity' => 3]]))
        ->toThrow(DomainException::class, 'Received quantity exceeds the remaining purchase order quantity.');
});

test('failed receipt rolls back receipt and inventory changes', function () {
    [$order, $item, $product, $location] = approvedPurchaseOrderForReceipt();
    $secondProduct = Product::factory()->create();
    $secondItem = PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'product_id' => $secondProduct->id,
        'quantity' => 5,
        'unit_cost' => 2000,
        'line_total' => 10000,
    ]);

    expect(fn () => app(GoodsReceiptService::class)->receive(
        $order,
        $location,
        'GR-ROLLBACK',
        [
            ['purchase_order_item_id' => $item->id, 'quantity' => 2],
            ['purchase_order_item_id' => $secondItem->id, 'quantity' => 6],
        ],
    ))->toThrow(DomainException::class);

    expect(GoodsReceipt::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0)
        ->and(InventoryStock::count())->toBe(0)
        ->and($item->refresh()->received_quantity)->toBe('0.000');
});
