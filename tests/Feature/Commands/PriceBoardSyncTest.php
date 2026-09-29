<?php

namespace Tests\Feature\Commands;

use App\Services\PriceBoardService;
use App\Services\PriceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Product\Models\Product;
use Tests\TestCase;

class PriceBoardSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tapsi.enabled' => true,
            'tapsi.base_url' => 'https://vendorgw.tapsi.shop/web/hub/vendors/v1',
            'tapsi.auth_token' => 'test-token',
            'tapsi.chunk_size' => 30,
            'tapsi.delay_between_chunks' => 0,
        ]);
    }

    private function fakeBoard(): void
    {
        $payload = [
            'prices' => [
                'Gold750_Sell' => [
                    'name' => 'قیمت فروش طلای ۷۵۰',
                    'value' => 1000,
                    'price_type' => 'sell',
                    'base_metal' => 'Gold750',
                    'trend' => 'stable',
                    'updated_at' => now()->timestamp,
                ],
            ],
            'debug' => [],
            'source_status' => [],
            'raw' => ['persian' => [], 'tala' => []],
            'used_fallback' => false,
        ];

        $board = Mockery::mock(PriceBoardService::class);
        $board->shouldReceive('fetchAndStore')->andReturn($payload)->byDefault();
        $board->shouldReceive('getBoardPrices')->andReturn($payload['prices'])->byDefault();
        $board->shouldReceive('getLastSyncAt')->andReturn(now())->byDefault();

        app()->instance(PriceBoardService::class, $board);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Product '.uniqid(),
            'slug' => 'product-'.uniqid(),
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold750_Sell',
            'weight' => '2.00',
            'price' => 1,
            'tokeniko_sku' => 'zioto-gold-bar-2gram',
            'tapsi_product_id' => 'ZGB5-0002-0',
            'stock_quantity' => 5,
            'hesabfa_physical_stock' => 5,
            'hesabfa_reserved_stock' => 0,
        ], $overrides));
    }

    public function test_dynamic_mode_prices_products_from_board_and_still_pushes_tapsi(): void
    {
        config(['pricing.mode' => 'dynamic']);

        $this->fakeBoard();
        $history = Mockery::spy(PriceHistoryService::class);
        app()->instance(PriceHistoryService::class, $history);

        $product = $this->product();

        $tapsiRequests = [];
        Http::fake([
            '*apigateway.tokeniko.com/*' => Http::response([
                'Model' => [['Name' => 'zioto-gold-bar-2gram', 'SellPrice' => 999_999_999]],
            ], 200),
            '*vendorgw.tapsi.shop/*' => function (Request $request) use (&$tapsiRequests) {
                $tapsiRequests[] = $request;

                return Http::response(['success' => true], 200);
            },
        ]);

        $this->artisan('priceboard:sync')->assertExitCode(0);

        // Board stores 1000 Rial = 100 Toman. 2g × 100 Toman × (1 + 1.0) = 400 Toman = 4000 Rial
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 4000]);

        $this->assertCount(0, Http::recorded()->filter(
            fn (array $pair) => str_contains($pair[0]->url(), 'apigateway.tokeniko.com')
        ));

        $this->assertCount(1, $tapsiRequests);
        $payload = $tapsiRequests[0]->data()['products'];
        $this->assertSame(4080, $payload[0]['price']);
        $this->assertSame(5, $payload[0]['stock']);

        $history->shouldHaveReceived('logBoardPrices');
    }

    public function test_direct_mode_still_writes_prices_from_the_tokeniko_shop_api(): void
    {
        config(['pricing.mode' => 'direct']);

        $this->fakeBoard();
        app()->instance(PriceHistoryService::class, Mockery::spy(PriceHistoryService::class));

        $product = $this->product(['price' => 1]);

        $tapsiRequests = [];
        Http::fake([
            '*apigateway.tokeniko.com/*' => Http::response([
                'Model' => [['Name' => 'zioto-gold-bar-2gram', 'SellPrice' => 300_000_000]],
            ], 200),
            '*vendorgw.tapsi.shop/*' => function (Request $request) use (&$tapsiRequests) {
                $tapsiRequests[] = $request;

                return Http::response(['success' => true], 200);
            },
        ]);

        $this->artisan('priceboard:sync')->assertExitCode(0);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 300_000_000]);
        $this->assertCount(1, $tapsiRequests);
    }

    public function test_dynamic_mode_pushes_only_products_whose_price_or_stock_changed(): void
    {
        config(['pricing.mode' => 'dynamic']);

        $this->fakeBoard();
        app()->instance(PriceHistoryService::class, Mockery::spy(PriceHistoryService::class));

        $product = $this->product();

        $tapsiRequests = [];
        Http::fake([
            '*vendorgw.tapsi.shop/*' => function (Request $request) use (&$tapsiRequests) {
                $tapsiRequests[] = $request;

                return Http::response(['success' => true], 200);
            },
        ]);

        $this->artisan('priceboard:sync')->assertExitCode(0);
        $this->assertCount(1, $tapsiRequests);

        $this->artisan('priceboard:sync')
            ->expectsOutputToContain('No product price/stock changes to push to Tapsi Shop.')
            ->assertExitCode(0);
        $this->assertCount(1, $tapsiRequests);

        Product::where('id', $product->id)->update(['hesabfa_physical_stock' => 9]);

        $this->artisan('priceboard:sync')->assertExitCode(0);
        $this->assertCount(2, $tapsiRequests);

        $payload = $tapsiRequests[1]->data()['products'];
        $this->assertCount(1, $payload);
        $this->assertSame($product->tapsi_product_id, $payload[0]['id']);
        $this->assertSame(9, $payload[0]['stock']);
    }

    public function test_dynamic_mode_skips_tapsi_when_auth_token_is_missing(): void
    {
        config(['pricing.mode' => 'dynamic', 'tapsi.auth_token' => '']);

        $this->fakeBoard();
        app()->instance(PriceHistoryService::class, Mockery::spy(PriceHistoryService::class));
        $this->product();

        Http::fake();

        $this->artisan('priceboard:sync')
            ->expectsOutputToContain('Tapsi auth token missing — skipped sending to Tapsi.')
            ->assertExitCode(0);

        $this->assertCount(0, Http::recorded()->filter(
            fn (array $pair) => str_contains($pair[0]->url(), 'vendorgw.tapsi.shop')
        ));
    }

    public function test_dynamic_mode_skips_tapsi_when_sync_is_disabled(): void
    {
        config(['pricing.mode' => 'dynamic', 'tapsi.enabled' => false]);

        $this->fakeBoard();
        app()->instance(PriceHistoryService::class, Mockery::spy(PriceHistoryService::class));
        $this->product();

        Http::fake();

        $this->artisan('priceboard:sync')
            ->expectsOutputToContain('Tapsi sync disabled — skipped sending to Tapsi.')
            ->assertExitCode(0);

        $this->assertCount(0, Http::recorded()->filter(
            fn (array $pair) => str_contains($pair[0]->url(), 'vendorgw.tapsi.shop')
        ));
    }

    public function test_dynamic_mode_reports_a_skipped_tapsi_push_when_the_lock_is_held(): void
    {
        config(['pricing.mode' => 'dynamic']);

        $this->fakeBoard();
        app()->instance(PriceHistoryService::class, Mockery::spy(PriceHistoryService::class));
        $this->product();

        Http::fake();

        $lock = Cache::lock('tokeniko:direct-sync', 300);
        $lock->get();

        $this->artisan('priceboard:sync')
            ->expectsOutputToContain('Previous sync job is still active — skipped pushing to Tapsi.')
            ->assertExitCode(0);

        $lock->release();
    }

    public function test_dynamic_mode_completes_when_the_broadcast_connection_is_broken(): void
    {
        config(['pricing.mode' => 'dynamic', 'broadcasting.default' => 'bogus-driver']);

        $this->fakeBoard();
        app()->instance(PriceHistoryService::class, Mockery::spy(PriceHistoryService::class));

        $product = $this->product(['price' => 1]);

        Http::fake([
            '*vendorgw.tapsi.shop/*' => Http::response(['success' => true], 200),
        ]);

        $this->artisan('priceboard:sync')->assertExitCode(0);

        // Board stores 1000 Rial = 100 Toman. 2g × 100 Toman × (1 + 1.0) = 400 Toman = 4000 Rial
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 4000]);
    }
}
