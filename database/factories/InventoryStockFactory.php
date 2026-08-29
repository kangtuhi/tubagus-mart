<?php

namespace Database\Factories;

use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'location_id' => InventoryLocation::factory(),
            'quantity' => 0,
            'reserved_quantity' => 0,
        ];
    }

    public function withQuantity(float $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    public function reserved(float $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'reserved_quantity' => $quantity,
        ]);
    }
}
