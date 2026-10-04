<?php

namespace Tests\Feature\Models;

use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user's province is derivable from their city, so UserAddress fills a
 * missing province_id from cities.province_id instead of storing a hole.
 */
class UserAddressProvinceTest extends TestCase
{
    use RefreshDatabase;

    private function province(string $name = 'تهران'): Province
    {
        return Province::create(['id' => 10, 'name' => $name, 'slug' => 'test-province']);
    }

    private function city(Province $province, string $name = 'تهران'): City
    {
        return City::create(['province_id' => $province->id, 'name' => $name, 'slug' => 'test-city']);
    }

    public function test_it_derives_the_province_from_the_city_when_missing(): void
    {
        $province = $this->province();
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => $this->city($province)->id,
        ]);

        $this->assertSame($province->id, $address->fresh()->province_id);
    }

    public function test_it_never_overwrites_an_explicit_province(): void
    {
        $tehran = $this->province('تهران');
        $alborz = Province::create(['id' => 11, 'name' => 'البرز', 'slug' => 'test-alborz']);
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => $alborz->id,
            'city_id' => $this->city($tehran, 'تهران')->id,
        ]);

        $this->assertSame($alborz->id, $address->fresh()->province_id);
    }

    public function test_it_leaves_the_province_null_without_a_city(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        $this->assertNull($address->fresh()->province_id);
    }

    public function test_it_derives_the_province_when_an_existing_row_is_saved(): void
    {
        $province = $this->province();
        $user = User::factory()->create();

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => null,
            'city_id' => null,
        ]);

        $this->assertNull($address->fresh()->province_id);

        // Re-saving the row (what the backfill command does) fills the province.
        $address->city_id = $this->city($province)->id;
        $address->save();

        $this->assertSame($province->id, $address->fresh()->province_id);
    }
}
