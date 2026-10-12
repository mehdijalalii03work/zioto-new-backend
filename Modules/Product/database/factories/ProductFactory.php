<?php

namespace Modules\Product\Database\Factories;

use App\Enums\Inventory\InventoryMode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\Product;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'price' => fake()->numberBetween(1000000, 50000000),
            'stock_quantity' => 0,
            'serial_prefix' => null,
            'inventory_mode' => InventoryMode::Manual->value,
            'track_serials' => false,
            'made_to_order' => false,
        ];
    }

    public function serial(string $prefix): static
    {
        return $this->state(fn (array $attributes): array => [
            'serial_prefix' => strtolower($prefix),
            'inventory_mode' => InventoryMode::Serial->value,
            'track_serials' => true,
        ]);
    }
}
