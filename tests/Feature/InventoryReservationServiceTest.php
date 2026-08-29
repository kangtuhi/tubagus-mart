<?php

use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Services\Inventory\InventoryReservationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reservation reduces available stock without reducing physical stock', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();
    $stock = InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 20,
        'reserved_quantity' => 3,
    ]);

    $stock = app(InventoryReservationService::class)->reserve($product, $location, 7);

    expect($stock->quantity)->toBe('20.000')
        ->and($stock->reserved_quantity)->toBe('10.000');
});

test('reservation cannot exceed available stock', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();
    InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 10,
        'reserved_quantity' => 7,
    ]);

    expect(fn () => app(InventoryReservationService::class)->reserve($product, $location, 4))
        ->toThrow(DomainException::class, 'Insufficient available stock for reservation.');
});

test('reservation can be released', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();
    InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 20,
        'reserved_quantity' => 8,
    ]);

    $stock = app(InventoryReservationService::class)->release($product, $location, 5);

    expect($stock->quantity)->toBe('20.000')
        ->and($stock->reserved_quantity)->toBe('3.000');
});

test('reservation release cannot exceed reserved stock', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();
    InventoryStock::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 20,
        'reserved_quantity' => 3,
    ]);

    expect(fn () => app(InventoryReservationService::class)->release($product, $location, 4))
        ->toThrow(DomainException::class, 'Release quantity exceeds reserved stock.');
});

test('reservation quantity must be positive', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    expect(fn () => app(InventoryReservationService::class)->reserve($product, $location, 0))
        ->toThrow(DomainException::class, 'Reservation quantity must be greater than zero.');
});

test('reservation requires an existing stock record', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    expect(fn () => app(InventoryReservationService::class)->reserve($product, $location, 1))
        ->toThrow(DomainException::class, 'No inventory stock record exists for this product and location.');
});
