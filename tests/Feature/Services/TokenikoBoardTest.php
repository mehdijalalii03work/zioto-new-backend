<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\Pricing\ApiClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenikoBoardTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value, string $type = 'number'): void
    {
        Setting::create([
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'category' => 'pricing',
            'label' => $key,
        ]);
    }

    private function enableTokenikoMode(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_tokeniko_board_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'tokeniko_board_enabled',
        ]);
    }

    private function fakeTokeniko(array $products): void
    {
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => Http::response(['products' => $products], 200),
        ]);
    }

    private function tokenikoProducts(): array
    {
        return [
            ['name' => 'Gold750', 'sellPrice' => 260000000, 'buyPrice' => 259000000],
            ['name' => 'Gold995', 'sellPrice' => 345000000, 'buyPrice' => 343000000],
            ['name' => 'Gold9999', 'sellPrice' => 348000000, 'buyPrice' => 346000000],
            ['name' => 'Gold999', 'sellPrice' => 347000000, 'buyPrice' => 345000000],
            ['name' => 'Silver9999', 'sellPrice' => 5130000, 'buyPrice' => 5080000],
            ['name' => 'Silver999', 'sellPrice' => 4930000, 'buyPrice' => 4880000],
            ['name' => 'Euro', 'sellPrice' => 2943300, 'buyPrice' => 2943300],
            ['name' => 'Silver 925', 'sellPrice' => 4880013, 'buyPrice' => 4733612],
            ['name' => 'Gold705', 'sellPrice' => 1129400000, 'buyPrice' => 1129400000],
            ['name' => 'Broken', 'sellPrice' => null, 'buyPrice' => null],
        ];
    }

    private function boardPrices(): array
    {
        return app(ApiClientService::class)->getAllPrices(forceRefresh: true)['prices'] ?? [];
    }

    public function test_tokeniko_values_land_on_board_directly_ignoring_manual_and_ratios(): void
    {
        $this->enableTokenikoMode();
        $this->setting('zioto_pricing_manual_gold750_sell', '99999999');
        $this->setting('zioto_pricing_manual_silver9999_sell', '99999999');
        $this->setting('zioto_pricing_coef_gold750_sell_ratio', '1.5');
        $this->setting('zioto_pricing_coef_buy_price', '0.5');
        $this->fakeTokeniko($this->tokenikoProducts());

        $prices = $this->boardPrices();

        $this->assertSame(260000000.0, $prices['Gold750_Sell']['value']);
        $this->assertSame(259000000.0, $prices['Gold750_Buy']['value']);
        $this->assertSame(5130000.0, $prices['Silver9999_Sell']['value']);
        $this->assertSame(4930000.0, $prices['Silver999_Sell']['value']);
        $this->assertSame(348000000.0, $prices['Gold9999_Sell']['value']);
        $this->assertSame(345000000.0, $prices['Gold995_Sell']['value']);
    }

    public function test_tokeniko_whitelist_ignores_unneeded_items(): void
    {
        $this->enableTokenikoMode();
        $this->fakeTokeniko($this->tokenikoProducts());

        $prices = $this->boardPrices();

        foreach (array_keys($prices) as $key) {
            $this->assertMatchesRegularExpression('/^(Gold750|Gold995|Gold9999|Gold999|Silver9999|Silver999)_(Sell|Buy)$/', $key);
        }
        $this->assertCount(12, $prices);
    }

    public function test_tokeniko_failure_falls_back_to_computed_board(): void
    {
        $this->enableTokenikoMode();
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => Http::response([], 500),
            '*persianapi*' => Http::response(['result' => [
                ['key' => 137120, 'price' => '244259000'],
            ]], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => [
                'geram18k' => ['value' => 24425900],
            ]], 200),
        ]);

        $service = app(ApiClientService::class);
        $prices = $service->getAllPrices(forceRefresh: true)['prices'] ?? [];

        $this->assertArrayHasKey('Gold750_Sell', $prices);
        $this->assertEqualsWithDelta(244259000.0 * 1.001, $prices['Gold750_Sell']['value'], 1.0);
        $this->assertSame('down', $service->getSourceStatus()['tokeniko']['status'] ?? null);
    }

    public function test_tokeniko_not_fetched_when_switch_is_off(): void
    {
        $tokenikoHits = 0;
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => function () use (&$tokenikoHits) {
                $tokenikoHits++;

                return Http::response(['products' => []], 200);
            },
            '*persianapi*' => Http::response(['result' => [
                ['key' => 137120, 'price' => '244259000'],
            ]], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => [
                'geram18k' => ['value' => 24425900],
            ]], 200),
        ]);

        $prices = $this->boardPrices();

        $this->assertSame(0, $tokenikoHits);
        $this->assertArrayHasKey('Gold750_Sell', $prices);
    }

    public function test_emergency_multiplier_still_applies_in_tokeniko_mode(): void
    {
        $this->enableTokenikoMode();
        Setting::create([
            'key' => 'zioto_pricing_emergency_mode_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'emergency_mode_enabled',
        ]);
        $this->setting('zioto_pricing_coef_emergency_gold', '1.02');
        $this->setting('zioto_pricing_coef_emergency_silver', '0.98');
        $this->fakeTokeniko($this->tokenikoProducts());

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(260000000.0 * 1.02, $prices['Gold750_Sell']['value'], 1.0);
        $this->assertEqualsWithDelta(5130000.0 * 0.98, $prices['Silver9999_Sell']['value'], 1.0);
    }

    public function test_tokeniko_source_status_and_raw_are_recorded(): void
    {
        $this->enableTokenikoMode();
        $this->fakeTokeniko($this->tokenikoProducts());

        $service = app(ApiClientService::class);
        $service->getAllPrices(forceRefresh: true);

        $this->assertSame('ok', $service->getSourceStatus()['tokeniko']['status'] ?? null);
        $this->assertSame(260000000.0, $service->getBasePrices()['tokeniko']['Gold750']['sell'] ?? null);
    }
}
