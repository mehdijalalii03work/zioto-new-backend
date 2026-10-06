<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\Pricing\ApiClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SilverBoardPricingTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value): void
    {
        Setting::create([
            'key' => $key,
            'value' => $value,
            'type' => 'number',
            'category' => 'pricing',
            'label' => $key,
        ]);
    }

    private function fakeSources(): void
    {
        Http::fake([
            '*persianapi*' => Http::response(['result' => [
                ['key' => 137120, 'price' => '244259000'],
                ['key' => 684758, 'price' => '999999999'],
            ]], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => [
                'geram18k' => ['value' => 24425900],
            ]], 200),
        ]);
    }

    private function boardPrices(): array
    {
        return app(ApiClientService::class)->getAllPrices(forceRefresh: true)['prices'] ?? [];
    }

    public function test_silver_9999_sell_comes_only_from_manual_toman_input(): void
    {
        $this->setting('zioto_pricing_manual_silver9999_sell', '5130000');
        $this->fakeSources();

        $prices = $this->boardPrices();

        // Toman input is converted to Rial on the board; the huge PersianAPI
        // silver value in the fixture must not leak into the price.
        $this->assertSame(51300000.0, $prices['Silver9999_Sell']['value']);
    }

    public function test_silver_999_derives_from_9999_with_configurable_coefficient(): void
    {
        $this->setting('zioto_pricing_manual_silver9999_sell', '5130000');
        $this->fakeSources();

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(49326899.4, $prices['Silver999_Sell']['value'], 1.0);

        $this->setting('zioto_pricing_coef_silver9999_to_silver999', '0.97');

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(49761000.0, $prices['Silver999_Sell']['value'], 1.0);
    }

    public function test_silver_buy_uses_silver_buy_coefficient_independent_of_gold(): void
    {
        $this->setting('zioto_pricing_manual_silver9999_sell', '5130000');
        $this->setting('zioto_pricing_coef_buy_price', '0.5');
        $this->setting('zioto_pricing_coef_silver_buy_price', '0.99');
        $this->fakeSources();

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(50787000.0, $prices['Silver9999_Buy']['value'], 1.0);
        $this->assertEqualsWithDelta(49326899.4 * 0.99, $prices['Silver999_Buy']['value'], 1.0);
    }

    public function test_no_silver_entries_when_manual_price_is_empty(): void
    {
        $this->fakeSources();

        $prices = $this->boardPrices();

        $this->assertArrayNotHasKey('Silver9999_Sell', $prices);
        $this->assertArrayNotHasKey('Silver9999_Buy', $prices);
        $this->assertArrayNotHasKey('Silver999_Sell', $prices);
        $this->assertArrayNotHasKey('Silver999_Buy', $prices);
        $this->assertArrayHasKey('Gold750_Sell', $prices);
    }
}
