<?php

use App\Models\InventoryStock;
use App\Services\Inventory\InventoryStockRules;
use DomainException;

function stockForRules(float $quantity, float $reserved): InventoryStock
{
    return new InventoryStock([
        'quantity' => $quantity,
        'reserved_quantity' => $reserved,
    ]);
}

test('available quantity is physical stock minus reserved stock', function () {
    $stock = stockForRules(25, 7);

    expect(app(InventoryStockRules::class)->availableQuantity($stock))->toBe(18.0);
});

test('negative physical stock is rejected', function () {
    expect(fn () => app(InventoryStockRules::class)->validate(stockForRules(-1, 0)))
        ->toThrow(DomainException::class, 'Stock quantity cannot be negative.');
});

test('negative reserved stock is rejected', function () {
    expect(fn () => app(InventoryStockRules::class)->validate(stockForRules(10, -1)))
        ->toThrow(DomainException::class, 'Reserved stock cannot be negative.');
});

test('reserved stock cannot exceed physical stock', function () {
    expect(fn () => app(InventoryStockRules::class)->validate(stockForRules(10, 11)))
        ->toThrow(DomainException::class, 'Reserved stock cannot exceed physical stock.');
});
