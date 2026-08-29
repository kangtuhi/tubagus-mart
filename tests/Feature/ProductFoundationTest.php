<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product foundation creates catalog master data', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductFoundationSeeder']);

    expect(Unit::query()->count())->toBe(2)
        ->and(ProductCategory::query()->count())->toBe(2)
        ->and(Brand::query()->count())->toBe(1)
        ->and(Product::query()->count())->toBe(2);
});

test('product relationships resolve correctly', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductFoundationSeeder']);

    $product = Product::query()->where('sku', 'TM-WATER-600')->firstOrFail();

    expect($product->category->code)->toBe('BEV')
        ->and($product->category->parent->code)->toBe('FOOD')
        ->and($product->brand->name)->toBe('Tubagus Mart')
        ->and($product->baseUnit->code)->toBe('PCS')
        ->and($product->selling_price)->toBe('3500.00');
});
