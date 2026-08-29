<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Product\ProductBusinessRules;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validProductAttributes(): array
{
    return [
        'sku' => 'TEST-001',
        'name' => 'Test Product',
        'slug' => 'test-product',
        'product_type' => ProductType::STOCK,
        'cost_price' => 1000,
        'selling_price' => 1500,
        'tax_rate' => 0,
        'is_taxable' => false,
        'track_stock' => true,
        'is_active' => true,
    ];
}

test('stock products can track stock', function () {
    $product = new Product(validProductAttributes());
    $product->baseUnit()->associate(Unit::factory()->create());

    expect(fn () => app(ProductBusinessRules::class)->validate($product))
        ->not->toThrow(DomainException::class);
});

test('service products cannot track stock', function () {
    $product = new Product(validProductAttributes());
    $product->product_type = ProductType::SERVICE;
    $product->baseUnit()->associate(Unit::factory()->create());

    expect(fn () => app(ProductBusinessRules::class)->validate($product))
        ->toThrow(DomainException::class, 'Service products cannot track stock.');
});

test('selling price cannot be below cost price', function () {
    $product = new Product(validProductAttributes());
    $product->selling_price = 900;
    $product->baseUnit()->associate(Unit::factory()->create());

    expect(fn () => app(ProductBusinessRules::class)->validate($product))
        ->toThrow(DomainException::class, 'Selling price cannot be lower than cost price.');
});

test('taxable products can have a valid tax rate', function () {
    $product = new Product(validProductAttributes());
    $product->is_taxable = true;
    $product->tax_rate = 12;
    $product->baseUnit()->associate(Unit::factory()->create());

    expect(fn () => app(ProductBusinessRules::class)->validate($product))
        ->not->toThrow(DomainException::class);
});

test('non taxable products must have zero tax rate', function () {
    $product = new Product(validProductAttributes());
    $product->tax_rate = 12;
    $product->baseUnit()->associate(Unit::factory()->create());

    expect(fn () => app(ProductBusinessRules::class)->validate($product))
        ->toThrow(DomainException::class, 'A non-taxable product must have a zero tax rate.');
});

test('product factory creates a stock product by default', function () {
    $product = Product::factory()->create();

    expect($product->product_type)->toBe(ProductType::STOCK)
        ->and($product->track_stock)->toBeTrue()
        ->and($product->baseUnit)->not->toBeNull();
});

test('product factory creates service products without stock tracking', function () {
    $product = Product::factory()->service()->create();

    expect($product->product_type)->toBe(ProductType::SERVICE)
        ->and($product->track_stock)->toBeFalse();
});

test('product factory creates non stock products without stock tracking', function () {
    $product = Product::factory()->nonStock()->create();

    expect($product->product_type)->toBe(ProductType::NON_STOCK)
        ->and($product->track_stock)->toBeFalse();
});
