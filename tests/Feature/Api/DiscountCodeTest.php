<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class DiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    public function test_customer_can_validate_a_fixed_discount_for_their_platform(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Product',
            'slug' => 'discount-product',
            'price' => 2_000_000,
            'stock_quantity' => 5,
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        DB::table('discount_codes')->insert([
            'code' => 'SAVE200',
            'discount_amount' => 200_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'max_uses' => 5,
            'max_uses_per_customer' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/discount-codes/validate', ['code' => 'SAVE200']);

        $response->assertOk()
            ->assertJsonPath('discount_amount', 200_000)
            ->assertJsonPath('eligible', true);
        $this->assertSame(0, Order::withoutTenantScope()->count());
    }

    public function test_order_creation_records_discount_usage_and_reduces_the_payable_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Product',
            'slug' => 'discount-order-product',
            'price' => 2_000_000,
            'stock_quantity' => 5,
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        DB::table('discount_codes')->insert([
            'code' => 'ORDER200',
            'discount_amount' => 200_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'max_uses' => 1,
            'max_uses_per_customer' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shippingMethod = ShippingMethod::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'ORDER200',
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.total_amount', '1800000')
            ->assertJsonPath('order.discount_amount', 200_000)
            ->assertJsonPath('order.discount_code', 'ORDER200');
        $this->assertDatabaseCount('discount_code_usages', 1);

        $retry = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'ORDER200',
        ]);
        $retry->assertOk()->assertJsonPath('is_existing', true);
        $this->assertDatabaseCount('discount_code_usages', 1);

        $anotherUser = User::factory()->create();
        Cart::withoutTenantScope()->create([
            'user_id' => $anotherUser->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        $overLimit = $this->withHeaders([
            'Authorization' => 'Bearer '.$anotherUser->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'ORDER200',
        ]);
        $overLimit->assertStatus(422)->assertJsonPath('error_code', 'DISCOUNT_CODE_INVALID');
        $this->assertDatabaseCount('discount_code_usages', 1);
    }

    public function test_validation_rejects_dates_platform_and_customer_or_global_usage_limits(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $codeId = DB::table('discount_codes')->insertGetId([
            'code' => 'LIMITED',
            'discount_amount' => 100_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'max_uses' => 1,
            'max_uses_per_customer' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = ['Authorization' => 'Bearer '.$user->api_token, 'X-Platform' => 'main'];
        $this->withHeaders($headers)->postJson('/api/discount-codes/validate', ['code' => 'LIMITED'])->assertStatus(422);

        DB::table('discount_codes')->where('id', $codeId)->update([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $this->withHeaders($headers)->postJson('/api/discount-codes/validate', ['code' => 'LIMITED'])->assertStatus(422);

        DB::table('discount_codes')->where('id', $codeId)->update([
            'ends_at' => null,
            'platforms' => json_encode(['nopay']),
        ]);
        $this->withHeaders($headers)->postJson('/api/discount-codes/validate', ['code' => 'LIMITED'])->assertStatus(422);

        DB::table('discount_codes')->where('id', $codeId)->update([
            'platforms' => json_encode(['main']),
        ]);
        DB::table('discount_code_usages')->insert([
            'discount_code_id' => $codeId,
            'user_id' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->withHeaders($headers)->postJson('/api/discount-codes/validate', ['code' => 'LIMITED'])->assertStatus(422);

        DB::table('discount_codes')->where('id', $codeId)->update(['max_uses' => 3]);
        DB::table('discount_code_usages')->insert([
            'discount_code_id' => $codeId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->withHeaders($headers)->postJson('/api/discount-codes/validate', ['code' => 'LIMITED'])->assertStatus(422);
    }

    public function test_installment_fee_quote_uses_the_discounted_invoice_total(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Fee Product',
            'slug' => 'discount-fee-product',
            'price' => 2_000_000,
            'stock_quantity' => 5,
        ]);
        $shippingMethod = ShippingMethod::factory()->create();
        DB::table('discount_codes')->insert([
            'code' => 'FEE200',
            'discount_amount' => 200_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/payment/calculate-fee', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_method_id' => $shippingMethod->id,
            'gateway' => 'parsian',
            'discount_code' => 'FEE200',
        ]);

        $response->assertOk()
            ->assertJsonPath('base_total', 1_800_000)
            ->assertJsonPath('discount_amount', 200_000);
    }

    public function test_discount_is_capped_at_the_invoice_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Small Product',
            'slug' => 'discount-cap-product',
            'price' => 100_000,
            'stock_quantity' => 5,
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        DB::table('discount_codes')->insert([
            'code' => 'HUGE',
            'discount_amount' => 500_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shippingMethod = ShippingMethod::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'HUGE',
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.total_amount', '0')
            ->assertJsonPath('order.discount_amount', 100_000);
    }
}
