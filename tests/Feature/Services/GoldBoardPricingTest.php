<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\Pricing\ApiClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoldBoardPricingTest extends TestCase
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

    private function neutralRatios(): void
    {
        $this->setting('zioto_pricing_coef_gold999_sell_ratio', '1.0');
        $this->setting('zioto_pricing_coef_gold999_buy_ratio', '1.0');
    }

    private function fakeSources(?string $persianPrice, int|string|null $talaValue): void
    {
        $persianItems = [['key' => 137120, 'price' => '244259000']];

        if ($persianPrice !== null) {
            $persianItems[] = ['key' => 137121, 'price' => $persianPrice];
        }

        $talaRates = ['geram18k' => ['value' => 24425900]];

        if ($talaValue !== null) {
            $talaRates['geram24k'] = ['value' => $talaValue];
        }

        Http::fake([
            '*persianapi*' => Http::response(['result' => $persianItems], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => $talaRates], 200),
        ]);
    }

    private function boardPrices(): array
    {
        return app(ApiClientService::class)->getAllPrices(forceRefresh: true)['prices'] ?? [];
    }

    public function test_gold999_sell_is_max_and_buy_is_min_of_the_two_sources(): void
    {
        $this->neutralRatios();
        $this->fakeSources('349828000', 35008886);

        $prices = $this->boardPrices();

        $this->assertSame(350088860.0, $prices['Gold999_Sell']['value']);
        $this->assertSame(349828000.0, $prices['Gold999_Buy']['value']);
    }

    public function test_gold999_falls_back_to_a_single_source(): void
    {
        $this->neutralRatios();
        $this->fakeSources('349828000', null);

        $prices = $this->boardPrices();

        $this->assertSame(349828000.0, $prices['Gold999_Sell']['value']);
        $this->assertSame(349828000.0, $prices['Gold999_Buy']['value']);
    }

    public function test_gold999_buy_uses_buy_coefficient_when_sources_agree(): void
    {
        $this->neutralRatios();
        $this->fakeSources('350088860', 35008886);

        $prices = $this->boardPrices();

        $this->assertSame(350088860.0, $prices['Gold999_Sell']['value']);
        $this->assertEqualsWithDelta(350088860.0 * 0.99, $prices['Gold999_Buy']['value'], 1.0);
    }

    public function test_gold999_derivatives_are_absent_when_24k_sources_are_missing(): void
    {
        $this->neutralRatios();
        $this->fakeSources(null, null);

        $prices = $this->boardPrices();

        $this->assertArrayNotHasKey('Gold999_Sell', $prices);
        $this->assertArrayNotHasKey('Gold999_Buy', $prices);
        $this->assertArrayNotHasKey('Gold995_Sell', $prices);
        $this->assertArrayNotHasKey('Gold9999_Sell', $prices);
        $this->assertArrayHasKey('Gold750_Sell', $prices);
    }

    public function test_gold995_and_gold9999_derive_from_gold999_with_ratios(): void
    {
        $this->setting('zioto_pricing_coef_gold999_sell_ratio', '1.0');
        $this->setting('zioto_pricing_coef_gold999_buy_ratio', '1.0');
        $this->fakeSources('349828000', 35008886);

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(350088860.0 * 1.005, $prices['Gold9999_Sell']['value'], 1.0);
        $this->assertEqualsWithDelta(349828000.0 * 1.005 * 0.995, $prices['Gold9999_Buy']['value'], 1.0);
        $this->assertEqualsWithDelta(350088860.0 * 0.995996, $prices['Gold995_Sell']['value'], 1.0);
        $this->assertEqualsWithDelta(349828000.0 * 0.995996 * 0.995, $prices['Gold995_Buy']['value'], 1.0);

        $this->setting('zioto_pricing_coef_gold999_to_gold9999', '1.01');
        $this->setting('zioto_pricing_coef_gold9999_sell_ratio', '1.002');

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(350088860.0 * 1.01 * 1.002, $prices['Gold9999_Sell']['value'], 1.0);
    }

    public function test_gold999_applies_configurable_sell_and_buy_ratios(): void
    {
        $this->setting('zioto_pricing_coef_gold999_sell_ratio', '1.002');
        $this->setting('zioto_pricing_coef_gold999_buy_ratio', '0.995');
        $this->fakeSources('349828000', 35008886);

        $prices = $this->boardPrices();

        $this->assertEqualsWithDelta(350088860.0 * 1.002, $prices['Gold999_Sell']['value'], 1.0);
        $this->assertEqualsWithDelta(349828000.0 * 0.995, $prices['Gold999_Buy']['value'], 1.0);
    }

    public function test_gold999_manual_toman_price_wins_when_above_max_api(): void
    {
        $this->neutralRatios();
        $this->setting('zioto_pricing_manual_gold999_sell', '35100000');
        $this->fakeSources('349828000', 35008886);

        $prices = $this->boardPrices();

        $this->assertSame(351000000.0, $prices['Gold999_Sell']['value']);
        $this->assertEqualsWithDelta(351000000.0 * 0.99, $prices['Gold999_Buy']['value'], 1.0);
    }

    public function test_gold999_manual_price_wins_even_when_below_max_api(): void
    {
        $this->neutralRatios();
        $this->setting('zioto_pricing_manual_gold999_sell', '34000000');
        $this->fakeSources('349828000', 35008886);

        $prices = $this->boardPrices();

        $this->assertSame(340000000.0, $prices['Gold999_Sell']['value']);
        $this->assertEqualsWithDelta(340000000.0 * 0.99, $prices['Gold999_Buy']['value'], 1.0);
    }

    public function test_gold750_manual_toman_price_wins_even_when_below_max_api(): void
    {
        $this->setting('zioto_pricing_manual_gold750_sell', '24000000');
        $this->fakeSources(null, null);

        $prices = $this->boardPrices();

        $this->assertSame(240000000.0, $prices['Gold750_Sell']['value']);
        $this->assertEqualsWithDelta(240000000.0 * 0.99, $prices['Gold750_Buy']['value'], 1.0);
    }
}
