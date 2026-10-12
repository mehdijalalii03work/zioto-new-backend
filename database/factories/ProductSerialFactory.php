<?php

namespace Database\Factories;

use App\Enums\Inventory\SerialStatus;
use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\Product;

/**
 * @extends Factory<ProductSerial>
 */
class ProductSerialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'tst'.str_pad((string) fake()->unique()->numberBetween(1, 99999999), 8, '0', STR_PAD_LEFT);

        return [
            'product_id' => Product::factory(),
            'serial_code' => $code,
            'serial_prefix' => 'tst',
            'status' => SerialStatus::Available->value,
        ];
    }
}
