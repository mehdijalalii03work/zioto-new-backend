<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    public function definition(): array
    {
        return [
            'id' => fake()->numberBetween(1, 999),
            'name' => fake()->city(),
            'slug' => fake()->slug(2).'-'.fake()->unique()->numberBetween(1000, 9999),
        ];
    }
}
