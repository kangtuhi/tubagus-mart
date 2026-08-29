<?php

use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('inventory stock belongs to a product and location', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();
    $stock = InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
    ]);

    expect($stock->product->is($product))->toBeTrue()
        ->and($stock->location->is($location))->toBeTrue();
});

test('inventory stock factory supports quantity and reservation states', function () {
    $stock = InventoryStock::factory()
        ->withQuantity(25.5)
        ->reserved(4.25)
        ->create();

    expect($stock->quantity)->toBe('25.500')
        ->and($stock->reserved_quantity)->toBe('4.250');
});

test('a product can have stock at multiple locations', function () {
    $product = Product::factory()->create();
    $firstLocation = InventoryLocation::factory()->create();
    $secondLocation = InventoryLocation::factory()->create();

    InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $firstLocation->id,
        'quantity' => 10,
    ]);

    InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $secondLocation->id,
        'quantity' => 7,
    ]);

    expect($product->inventoryStocks()->count())->toBe(2)
        ->and($product->inventoryStocks()->sum('quantity'))->toBe(17.0);
});
