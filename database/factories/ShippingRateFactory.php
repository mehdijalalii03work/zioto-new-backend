<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
class ShippingRateFactory extends Factory
{
    protected $model = ShippingRate::class;

    public function definition(): array
    {
        return [
            'shipping_method_id' => ShippingMethod::factory(),
            'rate_type' => 'flat',
            'base_rate' => fake()->numberBetween(50000, 500000),
            'estimated_days_min' => fake()->numberBetween(1, 3),
            'estimated_days_max' => fake()->numberBetween(4, 7),
        ];
    }
}
