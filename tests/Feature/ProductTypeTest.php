<?php

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product type is represented by a backed enum', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductFoundationSeeder']);

    $product = Product::query()->where('sku', 'TM-WATER-600')->firstOrFail();

    expect($product->product_type)->toBe(ProductType::STOCK)
        ->and($product->product_type->value)->toBe('stock');
});

test('product type enum exposes supported catalog types', function () {
    expect(ProductType::cases())->toHaveCount(3)
        ->and(ProductType::STOCK->value)->toBe('stock')
        ->and(ProductType::NON_STOCK->value)->toBe('non_stock')
        ->and(ProductType::SERVICE->value)->toBe('service');
});
