<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Verifies that only products flagged `is_nopay` can be purchased through
 * the nopay (BMIC installment) flow, on both the cart and order/payment
 * chokepoints. The 'main' platform must remain unrestricted.
 */
class NopayProductEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    private function nopayHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'nopay',
        ];
    }

    private function mainHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];
    }

    private function eligibleProduct(): Product
    {
        return Product::create([
            'name' => 'شمش مجاز نوپی',
            'slug' => 'nopay-eligible-'.uniqid(),
            'price' => 1000000,
            'stock_quantity' => 10,
            'is_nopay' => true,
        ]);
    }

    private function nonEligibleProduct(): Product
    {
        return Product::create([
            'name' => 'شمش عادی',
            'slug' => 'nopay-forbidden-'.uniqid(),
            'price' => 1000000,
            'stock_quantity' => 10,
            'is_nopay' => false,
        ]);
    }

    private function shippingMethod(): ShippingMethod
    {
        return ShippingMethod::factory()->create(['is_active' => true]);
    }

    public function test_nopay_cart_accepts_eligible_product(): void
    {
        $user = User::factory()->nopay()->create();
        $product = $this->eligibleProduct();

        $response = $this->withHeaders($this->nopayHeaders($user))
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $response->assertOk();
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'platform' => 'nopay',
        ]);
    }

    public function test_nopay_cart_rejects_non_eligible_product(): void
    {
        $user = User::factory()->nopay()->create();
        $product = $this->nonEligibleProduct();

        $response = $this->withHeaders($this->nopayHeaders($user))
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'PRODUCT_NOT_ELIGIBLE_FOR_NOPAY']);
        $this->assertDatabaseMissing('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_main_cart_accepts_non_eligible_product(): void
    {
        $user = User::factory()->create();
        $product = $this->nonEligibleProduct();

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $response->assertOk();
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'platform' => 'main',
        ]);
    }

    public function test_nopay_order_rejects_non_eligible_product(): void
    {
        $user = User::factory()->nopay()->create();
        $product = $this->nonEligibleProduct();
        $method = $this->shippingMethod();

        // Cart row pre-exists (e.g. added before the eligibility flag changed).
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'nopay',
        ]);

        $response = $this->withHeaders($this->nopayHeaders($user))
            ->postJson('/api/orders', [
                'gateway' => 'nopay',
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'PRODUCT_NOT_ELIGIBLE_FOR_NOPAY']);
        $this->assertSame(0, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_nopay_order_accepts_eligible_product(): void
    {
        $user = User::factory()->nopay()->create();
        $product = $this->eligibleProduct();
        $method = $this->shippingMethod();

        $response = $this->withHeaders($this->nopayHeaders($user))
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $response->assertOk();

        $response = $this->withHeaders($this->nopayHeaders($user))
            ->postJson('/api/orders', [
                'gateway' => 'nopay',
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(201);
        $order = Order::withoutTenantScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('nopay', $order->platform);
        $this->assertStringStartsWith('N-', $order->order_number);
    }

    public function test_main_order_accepts_non_eligible_product(): void
    {
        $user = User::factory()->create();
        $product = $this->nonEligibleProduct();
        $method = $this->shippingMethod();

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1]);
        $response->assertOk();

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'gateway' => 'parsian',
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(201);
        $this->assertSame(1, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_payment_init_with_nopay_gateway_rejects_non_eligible_order(): void
    {
        $user = User::factory()->create();
        $product = $this->nonEligibleProduct();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => $product->price,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'nopay',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'PRODUCT_NOT_ELIGIBLE_FOR_NOPAY']);
    }
}
