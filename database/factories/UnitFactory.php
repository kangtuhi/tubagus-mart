<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $code = fake()->unique()->regexify('[A-Z]{3,6}');

        return [
            'code' => $code,
            'name' => $name,
            'symbol' => strtolower(substr($code, 0, 3)),
            'decimal_places' => 0,
            'is_active' => true,
        ];
    }
}
