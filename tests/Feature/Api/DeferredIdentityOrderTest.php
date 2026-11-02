<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\ShahkarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;
use Tests\TestCase;

class DeferredIdentityOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    public function test_pending_user_is_verified_before_order_is_created(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldReceive('getIdentityInfo')->once()->with('0012345678', '1370-01-15')->andReturn([
                'success' => true,
                'info' => [
                    'first_name' => 'مریم',
                    'last_name' => 'رضایی',
                    'father_name' => 'رضا',
                    'gender' => 'MALE',
                    'birth_place' => 'تهران',
                ],
            ]);
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'gateway' => 'parsian',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'identity_verification_status' => 'verified',
            'first_name' => 'مریم',
            'last_name' => 'رضایی',
            'name' => 'مریم رضایی',
            'father_name' => 'رضا',
            'birth_place' => 'تهران',
        ]);
    }

    public function test_incomplete_identity_response_does_not_verify_user_or_create_order(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldReceive('getIdentityInfo')->once()->andReturn([
                'success' => true,
                'info' => ['first_name' => null, 'last_name' => null],
            ]);
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertStatus(503)->assertJsonPath('error_code', 'IDENTITY_SERVICE_UNAVAILABLE');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'identity_verification_status' => 'pending']);
    }

    public function test_concurrent_identity_lookup_is_rejected_without_second_provider_call(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();
        $lock = Cache::lock('identity-verification:user:'.$user->id, 30);
        $this->assertTrue($lock->get());
        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldNotReceive('getIdentityInfo');
        });

        try {
            $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
                'shipping_method_id' => $shippingMethod->id,
            ]);
        } finally {
            $lock->release();
        }

        $response->assertStatus(409)->assertJsonPath('error_code', 'IDENTITY_VERIFICATION_IN_PROGRESS');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changed_birth_date_during_inquiry_cannot_be_marked_verified(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1991-04-04',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('getIdentityInfo')->once()->andReturnUsing(function () use ($user): array {
                User::withoutTenantScope()->whereKey($user->id)->update(['birth_date' => '1992-05-06']);

                return ['success' => true, 'info' => ['first_name' => 'مریم', 'last_name' => 'رضایی']];
            });
        });

        $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDENTITY_INFORMATION_CHANGED');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'birth_date' => '1992-05-06', 'identity_verification_status' => 'pending']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_birth_date_mismatch_prevents_order_creation(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldReceive('getIdentityInfo')->once()->andReturn([
                'success' => false,
                'reason' => 'birth_date_mismatch',
                'message' => 'تاریخ تولد وارد شده صحیح نیست',
            ]);
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertUnprocessable()->assertJsonPath('error_code', 'BIRTH_DATE_MISMATCH');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_identity_service_failure_prevents_order_creation(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldReceive('getIdentityInfo')->once()->andReturn([
                'success' => false,
                'reason' => 'connection_error',
                'message' => 'سرویس هویتی موقتاً در دسترس نیست',
            ]);
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertStatus(503)->assertJsonPath('error_code', 'IDENTITY_SERVICE_UNAVAILABLE');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_saved_birth_date_returns_a_correctable_error(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldReceive('getIdentityInfo')->once()->andReturn([
                'success' => false,
                'reason' => 'invalid_date',
                'message' => 'فرمت تاریخ تولد نامعتبر است',
            ]);
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertUnprocessable()->assertJsonPath('error_code', 'BIRTH_DATE_INVALID');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_identity_information_prevents_order_creation_without_lookup(): void
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'national_code' => null,
            'birth_date' => null,
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldNotReceive('getIdentityInfo');
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertUnprocessable()->assertJsonPath('error_code', 'IDENTITY_INFORMATION_REQUIRED');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_discount_does_not_trigger_paid_identity_lookup(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1991-04-04',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldNotReceive('getIdentityInfo');
        });

        $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'NOTVALID',
        ])->assertUnprocessable()->assertJsonPath('error_code', 'DISCOUNT_CODE_INVALID');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_another_users_address_cannot_trigger_identity_lookup_or_be_used_for_order(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1991-04-04',
            'identity_verification_status' => 'pending',
        ]);
        $otherUser = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $otherUser->id]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldNotReceive('getIdentityInfo');
        });

        $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'user_address_id' => $address->id,
        ])->assertUnprocessable()->assertJsonPath('error_code', 'ADDRESS_INVALID');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_shipping_method_does_not_trigger_identity_lookup(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1991-04-04',
            'identity_verification_status' => 'pending',
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->inactive()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldNotReceive('getIdentityInfo');
        });

        $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ])->assertUnprocessable()->assertJsonPath('error_code', 'SHIPPING_METHOD_INVALID');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_verified_user_does_not_repeat_identity_lookup(): void
    {
        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'تست',
            'national_code' => '0012345678',
            'birth_date' => '1370-01-15',
            'identity_verification_status' => 'verified',
            'identity_verified_at' => now(),
        ]);
        $this->addProductToCart($user);
        $shippingMethod = ShippingMethod::factory()->create();

        $this->mock(ShahkarService::class, function ($mock): void {
            $mock->shouldNotReceive('getIdentityInfo');
        });

        $response = $this->withHeaders($this->authHeaders($user))->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);
    }

    /** @return array<string, string> */
    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => $user->platform,
        ];
    }

    private function addProductToCart(User $user): void
    {
        $product = Product::create([
            'name' => 'Identity test product',
            'slug' => 'identity-test-product-'.$user->id,
            'price' => 100000,
            'stock_quantity' => 5,
            'weight' => 1,
        ]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => $user->platform,
        ]);
    }
}
