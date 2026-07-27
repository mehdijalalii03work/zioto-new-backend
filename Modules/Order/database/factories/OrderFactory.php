<?php

namespace Modules\Order\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_amount' => fake()->numberBetween(10000, 5000000),
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'online',
        ];
    }
}
