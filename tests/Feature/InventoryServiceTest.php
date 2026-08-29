<?php

use App\Enums\InventoryMovementType;
use App\Enums\ProductType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inventoryProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes);
}

test('opening balance increases stock and records balance', function () {
    $product = inventoryProduct();
    $location = InventoryLocation::factory()->create();

    $movement = app(InventoryService::class)->recordMovement(
        $product,
        $location->id,
        InventoryMovementType::OPENING_BALANCE,
        10,
        1200,
    );

    expect($movement->quantity)->toBe('10.000')
        ->and($movement->balance_after)->toBe('10.000')
        ->and(InventoryStock::first()->quantity)->toBe('10.000');
});

test('purchase increases stock and sale decreases stock', function () {
    $product = inventoryProduct();
    $location = InventoryLocation::factory()->create();
    $service = app(InventoryService::class);

    $service->recordMovement($product, $location->id, InventoryMovementType::PURCHASE, 20);
    $sale = $service->recordMovement($product, $location->id, InventoryMovementType::SALE, 7);

    expect($sale->quantity)->toBe('-7.000')
        ->and($sale->balance_after)->toBe('13.000')
        ->and(InventoryStock::first()->quantity)->toBe('13.000');
});

test('returns and adjustments change stock in the expected direction', function () {
    $product = inventoryProduct();
    $location = InventoryLocation::factory()->create();
    $service = app(InventoryService::class);

    $service->recordMovement($product, $location->id, InventoryMovementType::OPENING_BALANCE, 10);
    $service->recordMovement($product, $location->id, InventoryMovementType::PURCHASE_RETURN, 2);
    $service->recordMovement($product, $location->id, InventoryMovementType::SALE_RETURN, 3);
    $service->recordMovement($product, $location->id, InventoryMovementType::ADJUSTMENT_OUT, 1);
    $movement = $service->recordMovement($product, $location->id, InventoryMovementType::ADJUSTMENT_IN, 4);

    expect($movement->balance_after)->toBe('14.000')
        ->and(InventoryMovement::count())->toBe(5);
});

test('sale cannot reduce stock below zero', function () {
    $product = inventoryProduct();
    $location = InventoryLocation::factory()->create();
    $service = app(InventoryService::class);

    $service->recordMovement($product, $location->id, InventoryMovementType::OPENING_BALANCE, 5);

    expect(fn () => $service->recordMovement(
        $product,
        $location->id,
        InventoryMovementType::SALE,
        6,
    ))->toThrow(DomainException::class, 'Insufficient stock for this movement.');
});

test('non stock products cannot receive inventory movements', function () {
    $product = inventoryProduct([
        'product_type' => ProductType::NON_STOCK,
        'track_stock' => false,
    ]);
    $location = InventoryLocation::factory()->create();

    expect(fn () => app(InventoryService::class)->recordMovement(
        $product,
        $location->id,
        InventoryMovementType::OPENING_BALANCE,
        5,
    ))->toThrow(DomainException::class, 'This product does not track stock.');
});

test('movement quantity must be positive', function () {
    $product = inventoryProduct();
    $location = InventoryLocation::factory()->create();

    expect(fn () => app(InventoryService::class)->recordMovement(
        $product,
        $location->id,
        InventoryMovementType::PURCHASE,
        0,
    ))->toThrow(DomainException::class, 'Movement quantity must be greater than zero.');
});
