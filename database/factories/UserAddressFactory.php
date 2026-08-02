<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    public function definition(): array
    {
        // Use lazy factory attributes (NOT eager create() inside definition —
        // that breaks Laravel's factory state evaluation).
        return [
            'user_id' => User::factory(),
            'platform' => 'main',
            'label' => fake()->word(),
            'province_id' => Province::factory(),
            'city_id' => City::factory(),
            'district' => fake()->streetName(),
            'postal_code' => fake()->numerify('##########'),
            'address_line' => fake()->streetAddress(),
            'plate' => (string) fake()->numberBetween(1, 999),
            'unit' => null,
            'receiver_name' => fake()->name(),
            'receiver_phone' => '09'.fake()->numerify('#########'),
            'receiver_national_code' => null,
            'is_default' => false,
            'is_billing' => false,
        ];
    }
}
