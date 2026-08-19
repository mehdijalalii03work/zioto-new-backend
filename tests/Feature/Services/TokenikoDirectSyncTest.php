<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\TokenikoDirectSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Product\Models\Product;
use Tests\TestCase;

class TokenikoDirectSyncTest extends TestCase
{
    use RefreshDatabase;

    private TokenikoDirectSyncService $service;

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

        $this->service = app(TokenikoDirectSyncService::class);
    }

    private function product(string $tokenikoSku, ?string $tapsiId, float $price, int $physical = 5, int $reserved = 1): Product
    {
        return Product::create([
            'name' => 'Product '.uniqid(),
            'slug' => 'product-'.uniqid(),
            'tokeniko_sku' => $tokenikoSku,
            'tapsi_product_id' => $tapsiId,
            'price' => $price,
            'stock_quantity' => $physical,
            'hesabfa_physical_stock' => $physical,
            'hesabfa_reserved_stock' => $reserved,
            'hesabfa_manual_reserved' => 0,
        ]);
    }

    private function fakeTokeniko(array $prices, array &$tapsiRequests): void
    {
        $model = collect($prices)->map(fn (float $sellPrice, string $name) => [
            'Name' => $name,
            'SellPrice' => $sellPrice,
        ])->values()->all();

        Http::fake([
            '*apigateway.tokeniko.com/*' => Http::response(['Model' => $model], 200),
            '*vendorgw.tapsi.shop/*' => function (Request $request) use (&$tapsiRequests) {
                $tapsiRequests[] = $request;

                return Http::response(['success' => true], 200);
            },
        ]);
    }

    public function test_sync_updates_db_and_sends_all_products_to_tapsi(): void
    {
        $changed = $this->product('zioto-gold-bar-1gram-995', 'ZGB5-0001-0', 250_000_000, physical: 5, reserved: 1);
        $unchanged = $this->product('zioto-silver-bar-5gram', 'ZSB9-0005-0', 26_950_000, physical: 5, reserved: 0);

        $tapsiRequests = [];
        $this->fakeTokeniko([
            'zioto-gold-bar-1gram-995' => 300_000_000,
            'zioto-silver-bar-5gram' => 26_950_000,
        ], $tapsiRequests);

        $result = $this->service->sync();

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, $result['tapsi_sent']);
        $this->assertTrue($result['tapsi_success']);

        $this->assertDatabaseHas('products', ['id' => $changed->id, 'price' => 300_000_000]);
        $this->assertDatabaseHas('products', ['id' => $unchanged->id, 'price' => 26_950_000]);

        $this->assertCount(1, $tapsiRequests);
        $payload = $tapsiRequests[0]->data()['products'];
        $this->assertCount(2, $payload);

        $changedPayload = collect($payload)->firstWhere('id', 'ZGB5-0001-0');
        $this->assertSame(303_000_000, $changedPayload['price']);
        $this->assertSame(4, $changedPayload['stock']);

        $unchangedPayload = collect($payload)->firstWhere('id', 'ZSB9-0005-0');
        $this->assertSame(27_489_000, $unchangedPayload['price']);
        $this->assertSame(5, $unchangedPayload['stock']);
    }

    public function test_sync_sends_products_to_tapsi_even_when_prices_unchanged(): void
    {
        $this->product('zioto-gold-bar-1gram-995', 'ZGB5-0001-0', 300_000_000, physical: 5, reserved: 1);

        $tapsiRequests = [];
        $this->fakeTokeniko(['zioto-gold-bar-1gram-995' => 300_000_000], $tapsiRequests);

        $result = $this->service->sync();

        $this->assertSame('success', $result['status']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['tapsi_sent']);
        $this->assertCount(1, $tapsiRequests);
        $this->assertSame(303_000_000, $tapsiRequests[0]->data()['products'][0]['price']);
    }

    public function test_sync_sends_zero_stock_for_all_products_when_emergency_active(): void
    {
        Setting::create([
            'key' => 'tapsi_emergency_status',
            'value' => 'closed',
            'type' => 'string',
            'category' => 'tapsi',
            'label' => 'Tapsi Emergency Status',
        ]);

        $product = $this->product('zioto-gold-bar-1gram-995', 'ZGB5-0001-0', 300_000_000, physical: 5, reserved: 1);

        $tapsiRequests = [];
        $this->fakeTokeniko(['zioto-gold-bar-1gram-995' => 300_000_000], $tapsiRequests);

        $result = $this->service->sync();

        $this->assertSame('success', $result['status']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['tapsi_sent']);
        $this->assertCount(1, $tapsiRequests);
        $this->assertSame(0, $tapsiRequests[0]->data()['products'][0]['stock']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 300_000_000]);
    }

    public function test_sync_updates_products_sharing_the_same_tokeniko_sku(): void
    {
        $this->product('zioto-silver-bar-2.5gram', 'ZSB9-0002-5', 14_080_000);
        $giftPack = $this->product('zioto-silver-bar-2.5gram', 'ZSB9-0002-5-GK', 14_080_000);

        $tapsiRequests = [];
        $this->fakeTokeniko(['zioto-silver-bar-2.5gram' => 15_000_000], $tapsiRequests);

        $result = $this->service->sync();

        $this->assertSame(2, $result['updated']);
        $this->assertSame(2, $result['tapsi_sent']);
        $this->assertDatabaseHas('products', ['id' => $giftPack->id, 'price' => 15_000_000]);
    }

    public function test_sync_returns_failure_when_tokeniko_api_is_unavailable(): void
    {
        $this->product('zioto-gold-bar-1gram-995', 'ZGB5-0001-0', 300_000_000);

        Http::fake([
            '*apigateway.tokeniko.com/*' => Http::response([], 500),
        ]);

        $result = $this->service->sync();

        $this->assertSame('failure', $result['status']);
        $this->assertSame(0, $result['updated']);

        $tapsiCalls = Http::recorded()->filter(
            fn (array $pair) => str_contains($pair[0]->url(), 'vendorgw.tapsi.shop')
        );
        $this->assertCount(0, $tapsiCalls);
    }

    public function test_sync_skips_when_another_sync_holds_the_lock(): void
    {
        $product = $this->product('zioto-gold-bar-1gram-995', 'ZGB5-0001-0', 300_000_000);

        $lock = Cache::lock('tokeniko:direct-sync', 300);
        $lock->get();

        $tapsiRequests = [];
        $this->fakeTokeniko(['zioto-gold-bar-1gram-995' => 310_000_000], $tapsiRequests);

        $result = $this->service->sync();

        $this->assertSame('skipped', $result['status']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 300_000_000]);
        $this->assertCount(0, $tapsiRequests);

        $lock->release();
    }

    public function test_command_refreshes_price_board_and_syncs_products(): void
    {
        $this->product('zioto-gold-bar-1gram-995', 'ZGB5-0001-0', 250_000_000);

        $tapsiRequests = [];

        Http::fake([
            '*apigateway.tokeniko.com/*' => Http::response([
                'Model' => [['Name' => 'zioto-gold-bar-1gram-995', 'SellPrice' => 300_000_000]],
            ], 200),
            '*tokeniko.com/*' => Http::response([
                ['name' => 'Gold', 'sellPrice' => 100_000_000],
            ], 200),
            '*vendorgw.tapsi.shop/*' => function (Request $request) use (&$tapsiRequests) {
                $tapsiRequests[] = $request;

                return Http::response(['success' => true], 200);
            },
        ]);

        $this->artisan('tokeniko:sync-direct')
            ->expectsOutputToContain('Updated 1 products in DB.')
            ->expectsOutputToContain('Sent 1 products to Tapsi Shop (success).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', ['price' => 300_000_000]);
        $this->assertNotEmpty(Cache::get('priceboard:prices'));
        $this->assertCount(1, $tapsiRequests);
    }
}
