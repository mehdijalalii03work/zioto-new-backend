<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\City;
use App\Models\Province;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->api_token];
    }

    public function test_methods_returns_only_active(): void
    {
        $user = User::factory()->create();
        $province = Province::factory()->create();
        $city = City::factory()->create(['province_id' => $province->id]);
        UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'city_id' => $city->id,
        ]);

        $active = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create(['shipping_method_id' => $active->id, 'rate_type' => 'flat']);

        ShippingMethod::factory()->inactive()->create();

        $response = $this->withHeaders($this->authHeaders($user))->getJson('/api/shipping/methods');

        $response->assertOk()
            ->assertJsonCount(1, 'methods');
    }

    public function test_methods_filters_excluded_cities(): void
    {
        $user = User::factory()->create();
        $province = Province::factory()->create();
        $city = City::factory()->create(['province_id' => $province->id]);
        UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'city_id' => $city->id,
        ]);

        $method = ShippingMethod::factory()->create([
            'is_active' => true,
            'exclude_cities' => [$city->id],
        ]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'flat',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))->getJson('/api/shipping/methods');

        $response->assertOk()
            ->assertJsonCount(0, 'methods');
    }

    public function test_methods_returns_only_pickup_when_no_address(): void
    {
        $user = User::factory()->create();

        $pickup = ShippingMethod::factory()->pickup()->create(['is_active' => true]);
        ShippingRate::factory()->create(['shipping_method_id' => $pickup->id, 'rate_type' => 'flat']);

        $regular = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create(['shipping_method_id' => $regular->id, 'rate_type' => 'flat']);

        $response = $this->withHeaders($this->authHeaders($user))->getJson('/api/shipping/methods');

        $response->assertOk()
            ->assertJsonCount(1, 'methods')
            ->assertJsonPath('methods.0.id', $pickup->id);
    }

    public function test_calculate_flat_rate(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'flat',
            'base_rate' => 75000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_total' => 1000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('shipping_cost', 75000)
            ->assertJsonPath('total_shipping_cost', 75000);
    }

    public function test_calculate_weight_based_rate(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'weight',
            'min_weight' => 0,
            'max_weight' => 5000,
            'base_rate' => 50000,
            'per_kg_rate' => 10000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_items' => [
                ['product_id' => 1, 'quantity' => 2, 'weight' => 1500],
            ],
            'cart_total' => 1000000,
        ]);

        // 2 * 1500g = 3000g, extra_kg = ceil(3000/1000) - 1 = 2
        // 50000 + 2 * 10000 = 70000
        $response->assertOk()
            ->assertJsonPath('shipping_cost', 70000);
    }

    public function test_calculate_free_shipping(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'cart_total',
            'min_cart_total' => 0,
            'max_cart_total' => 100000000,
            'base_rate' => 80000,
            'free_shipping_min' => 5000000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_total' => 5000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('shipping_cost', 0)
            ->assertJsonPath('free_shipping', true);
    }

    public function test_calculate_free_shipping_max(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'cart_total',
            'min_cart_total' => 0,
            'max_cart_total' => 100000000,
            'base_rate' => 80000,
            'free_shipping_max' => 4000000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_total' => 4000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('shipping_cost', 0)
            ->assertJsonPath('free_shipping', true);
    }

    public function test_calculate_free_shipping_max_not_applied_when_over(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'cart_total',
            'min_cart_total' => 0,
            'max_cart_total' => 100000000,
            'base_rate' => 80000,
            'free_shipping_max' => 4000000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_total' => 5000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('shipping_cost', 80000)
            ->assertJsonPath('free_shipping', false);
    }

    public function test_calculate_post_precious_has_no_insurance(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true, 'code' => 'post_precious']);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'flat',
            'base_rate' => 50000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_total' => 40000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('shipping_cost', 50000)
            ->assertJsonMissingPath('has_insurance')
            ->assertJsonMissingPath('insurance_cost')
            ->assertJsonPath('total_shipping_cost', 50000);
    }

    public function test_calculate_post_precious_high_value_cart(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true, 'code' => 'post_precious']);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'flat',
            'base_rate' => 50000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_total' => 60000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('shipping_cost', 50000)
            ->assertJsonMissingPath('has_insurance')
            ->assertJsonMissingPath('insurance_cost')
            ->assertJsonPath('total_shipping_cost', 50000);
    }

    public function test_calculate_returns_404_when_no_rate_found(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'weight',
            'min_weight' => 5,
            'max_weight' => 10,
            'base_rate' => 50000,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_items' => [
                ['product_id' => 1, 'quantity' => 1, 'weight' => 500],
            ],
            'cart_total' => 100000,
        ]);

        $response->assertNotFound()
            ->assertJson(['error_code' => 'SHIPPING_RATE_NOT_FOUND']);
    }

    public function test_calculate_returns_breakdown(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingRate::factory()->create([
            'shipping_method_id' => $method->id,
            'rate_type' => 'weight',
            'min_weight' => 0,
            'max_weight' => 5000,
            'base_rate' => 50000,
            'per_kg_rate' => 10000,
            'tax_rate' => 9,
        ]);

        $response = $this->postJson('/api/shipping/calculate', [
            'shipping_method_id' => $method->id,
            'cart_items' => [
                ['product_id' => 1, 'quantity' => 3, 'weight' => 1000],
            ],
            'cart_total' => 1000000,
        ]);

        // 3 * 1000g = 3000g, extra_kg = ceil(3000/1000) - 1 = 2
        // base = 50000, weight_surcharge = 20000, subtotal = 70000
        // tax = round(70000 * 9 / 100) = 6300
        // total = 76300
        $response->assertOk()
            ->assertJsonPath('breakdown.base_rate', 50000)
            ->assertJsonPath('breakdown.tax', 6300)
            ->assertJsonPath('has_tax', true)
            ->assertJsonPath('total_shipping_cost', 76300);
    }

    public function test_show_method(): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/shipping/methods/'.$method->id);

        $response->assertOk()
            ->assertJsonPath('method.id', $method->id);
    }
}
