<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Tests\TestCase;

class WishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    public function test_toggle_adds_an_existing_product_to_the_authenticated_users_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Wishlist product',
            'slug' => 'wishlist-product',
            'price' => 100_000,
            'stock_quantity' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/wishlist/toggle', ['product_id' => $product->id]);

        $response->assertOk()->assertJsonPath('added', true);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'platform' => 'main',
        ]);
    }

    public function test_toggle_removes_an_existing_wishlist_entry(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Wishlist product',
            'slug' => 'wishlist-product',
            'price' => 100_000,
            'stock_quantity' => 1,
        ]);
        $user->wishlists()->attach($product->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/wishlist/toggle', ['product_id' => $product->id]);

        $response->assertOk()->assertJsonPath('added', false);
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }
}
