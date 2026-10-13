<?php

namespace Database\Factories;

use App\Models\ProductInventoryAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\Product;

/**
 * @extends Factory<ProductInventoryAudit>
 */
class ProductInventoryAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'actor_id' => User::factory(),
            'reason' => fake()->sentence(),
            'before' => ['stock_quantity' => 5],
            'after' => ['stock_quantity' => 4],
        ];
    }
}
