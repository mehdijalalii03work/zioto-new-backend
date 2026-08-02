<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    protected $model = ShippingMethod::class;

    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 999),
            'code' => fake()->unique()->word(),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_pickup' => false,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pickup' => true,
        ]);
    }
}
