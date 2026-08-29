<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'code' => strtoupper(fake()->unique()->bothify('SUP-####')),
            'name' => $name,
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'payment_terms' => 'NET 30',
            'credit_limit' => 0,
            'tax_number' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withCreditLimit(float $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => $limit,
        ]);
    }
}
