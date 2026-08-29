<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ProductFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $piece = Unit::query()->updateOrCreate(['code' => 'PCS'], [
            'name' => 'Piece',
            'symbol' => 'pcs',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $kilogram = Unit::query()->updateOrCreate(['code' => 'KG'], [
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'decimal_places' => 3,
            'is_active' => true,
        ]);

        $food = ProductCategory::query()->updateOrCreate(['code' => 'FOOD'], [
            'name' => 'Food',
            'slug' => 'food',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $beverages = ProductCategory::query()->updateOrCreate(['code' => 'BEV'], [
            'parent_id' => $food->id,
            'name' => 'Beverages',
            'slug' => 'beverages',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $brand = Brand::query()->updateOrCreate(['name' => 'Tubagus Mart'], [
            'slug' => 'tubagus-mart',
            'description' => 'House brand for Tubagus Mart products.',
            'is_active' => true,
        ]);

        Product::query()->updateOrCreate(['sku' => 'TM-WATER-600'], [
            'category_id' => $beverages->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $piece->id,
            'barcode' => '899000000001',
            'name' => 'Tubagus Mineral Water 600ml',
            'slug' => 'tubagus-mineral-water-600ml',
            'product_type' => 'stock',
            'cost_price' => 2500,
            'selling_price' => 3500,
            'is_taxable' => false,
            'track_stock' => true,
            'is_active' => true,
        ]);

        Product::query()->updateOrCreate(['sku' => 'TM-RICE-1KG'], [
            'category_id' => $food->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $kilogram->id,
            'barcode' => '899000000002',
            'name' => 'Tubagus Premium Rice 1kg',
            'slug' => 'tubagus-premium-rice-1kg',
            'product_type' => 'stock',
            'cost_price' => 14000,
            'selling_price' => 17000,
            'is_taxable' => false,
            'track_stock' => true,
            'is_active' => true,
        ]);
    }
}
