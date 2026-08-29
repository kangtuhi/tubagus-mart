<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'base_unit_id' => Unit::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('TEST-####')),
            'barcode' => fake()->unique()->numerify('899############'),
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'description' => fake()->optional()->sentence(),
            'product_type' => ProductType::STOCK,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'tax_rate' => 0,
            'is_taxable' => false,
            'track_stock' => true,
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => ProductType::SERVICE,
            'track_stock' => false,
        ]);
    }

    public function nonStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => ProductType::NON_STOCK,
            'track_stock' => false,
        ]);
    }
}
