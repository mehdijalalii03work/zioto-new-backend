<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\City;
use App\Models\DigiExpressCity;
use App\Models\DigiExpressCityMapping;
use App\Models\Province;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigiExpressShippingCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    public function test_generic_address_shipping_requires_a_verified_mapping_and_is_hidden_in_tehran(): void
    {
        $user = User::factory()->create();
        $tehranProvince = Province::factory()->create(['id' => 8, 'name' => 'تهران', 'slug' => 'tehran']);
        $tehran = City::factory()->create(['province_id' => $tehranProvince->id, 'name' => 'تهران']);
        $otherProvince = Province::factory()->create(['id' => 31, 'name' => 'یزد', 'slug' => 'yazd']);
        $otherCity = City::factory()->create(['province_id' => $otherProvince->id, 'name' => 'یزد']);

        $pickup = ShippingMethod::factory()->pickup()->create(['code' => 'in_store_pickup']);
        $peyk = ShippingMethod::factory()->create(['code' => 'peyk']);
        $addressShipping = ShippingMethod::query()->where('code', 'digiexpress_address')->firstOrFail();
        ShippingRate::factory()->create(['shipping_method_id' => $pickup->id]);
        ShippingRate::factory()->create(['shipping_method_id' => $peyk->id]);
        ShippingRate::factory()->create(['shipping_method_id' => $addressShipping->id]);

        DigiExpressCity::query()->create([
            'external_id' => 'dx-yazd',
            'name' => 'یزد',
            'state_id' => '31',
            'state_name' => 'یزد',
        ]);
        DigiExpressCityMapping::query()->create([
            'city_id' => $otherCity->id,
            'digiexpress_city_id' => 'dx-yazd',
            'is_verified' => true,
            'is_destination_enabled' => true,
        ]);

        $nonTehranResponse = $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token])
            ->getJson('/api/shipping/methods?city_id='.$otherCity->id.'&province_id='.$otherProvince->id);

        $nonTehranResponse->assertOk()
            ->assertJsonFragment(['id' => $pickup->id])
            ->assertJsonFragment(['id' => $addressShipping->id])
            ->assertJsonMissing(['id' => $peyk->id]);

        $tehranResponse = $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token])
            ->getJson('/api/shipping/methods?city_id='.$tehran->id.'&province_id='.$tehranProvince->id);

        $tehranResponse->assertOk()
            ->assertJsonFragment(['id' => $pickup->id])
            ->assertJsonFragment(['id' => $peyk->id])
            ->assertJsonMissing(['id' => $addressShipping->id]);
    }

    public function test_generic_address_shipping_is_not_returned_for_unverified_mapping(): void
    {
        $user = User::factory()->create();
        $province = Province::factory()->create(['id' => 31]);
        $city = City::factory()->create(['province_id' => $province->id]);
        $shipping = ShippingMethod::query()->where('code', 'digiexpress_address')->firstOrFail();
        ShippingRate::factory()->create(['shipping_method_id' => $shipping->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token])
            ->getJson('/api/shipping/methods?city_id='.$city->id.'&province_id='.$province->id);

        $response->assertOk()->assertJsonMissing(['id' => $shipping->id]);
    }
}
