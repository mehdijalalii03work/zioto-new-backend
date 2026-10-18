<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\Pricing\DynamicPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DynamicPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedBoard(string $metalKey, float $value): void
    {
        Cache::put('zioto:payload', [
            'prices' => [
                $metalKey => [
                    'name' => 'قیمت فروش طلای ۷۵۰',
                    'value' => $value,
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
        ], 60);
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
        ], $overrides));
    }

    public function test_price_is_returned_in_rial_while_board_is_toman(): void
    {
        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        // Board stores 1000 Rial = 100 Toman. 2g × 100 Toman × (1 + 1.0) = 400 Toman = 4000 Rial
        $this->assertSame(4000.0, app(DynamicPriceService::class)->priceFor($product));
    }

    public function test_details_for_preview_stays_in_toman(): void
    {
        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        $details = app(DynamicPriceService::class)->detailsFor($product, 'basic', 'daily');

        $this->assertNotNull($details);
        // Board stores 1000 Rial = 100 Toman. 2g × 100 × 2 = 400 Toman
        $this->assertSame(400.0, (float) $details['calculated_price']);
        $this->assertSame(100.0, (float) $details['base_price']);
    }

    public function test_returns_null_when_dynamic_pricing_is_disabled(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_enable_dynamic_pricing',
            'value' => 'false',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'فعال‌سازی قیمت‌گذاری پویا',
        ]);

        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        $this->assertNull(app(DynamicPriceService::class)->priceFor($product));
    }

    public function test_tax_and_step_rounding_are_applied_in_toman_before_converting(): void
    {
        Setting::create([
            'key' => 'tax_gold',
            'value' => '10',
            'type' => 'number',
            'category' => 'pricing',
            'label' => 'مالیات طلا',
        ]);
        Setting::create([
            'key' => 'zioto_pricing_round_prices',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'گردکردن قیمت محصول نهایی',
        ]);
        Setting::create([
            'key' => 'zioto_pricing_round_to',
            'value' => '1000',
            'type' => 'integer',
            'category' => 'pricing',
            'label' => 'گردکردن به مضرب',
        ]);

        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        // 400 Toman → +10% tax = 440 → step 1000 = 0 Toman → 0 Rial
        $this->assertSame(0.0, app(DynamicPriceService::class)->priceFor($product));
    }

    public function test_gold_labor_tax_is_added_on_the_labor_of_dynamic_products(): void
    {
        Setting::create([
            'key' => 'tax_gold_labor',
            'value' => '10',
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات اجرت طلا',
        ]);

        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        // 400 Toman, labor = 200 → +10% labor tax = 20 → 420 Toman → 4200 Rial
        $this->assertSame(4200.0, app(DynamicPriceService::class)->priceFor($product));

        $details = app(DynamicPriceService::class)->detailsFor($product, 'basic', 'daily');

        $this->assertSame(420.0, (float) $details['calculated_price']);

        $matrix = app(DynamicPriceService::class)->previewMatrix($product, [['slug' => 'daily']], [['slug' => 'basic']]);

        $this->assertSame(420.0, (float) $matrix['daily']['basic']['final_price']);

        $taxes = $matrix['daily']['basic']['taxes'];

        $this->assertCount(1, $taxes);
        $this->assertSame('labor', $taxes[0]['scope']);
        $this->assertSame(10.0, $taxes[0]['rate']);
        $this->assertSame(20.0, $taxes[0]['amount']);
    }

    public function test_gold_labor_tax_is_skipped_for_silver_and_static_products(): void
    {
        Setting::create([
            'key' => 'tax_gold_labor',
            'value' => '10',
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات اجرت طلا',
        ]);

        $this->seedBoard('Silver9999_Sell', 1000);
        $silver = $this->product(['price_board_item' => 'Silver9999_Sell']);

        // 400 Toman → 4000 Rial (no gold labor tax on silver)
        $this->assertSame(4000.0, app(DynamicPriceService::class)->priceFor($silver));

        $silverMatrix = app(DynamicPriceService::class)->previewMatrix($silver, [['slug' => 'daily']], [['slug' => 'basic']]);

        $this->assertSame([], $silverMatrix['daily']['basic']['taxes']);

        $this->seedBoard('Gold750_Sell', 1000);
        $static = $this->product(['price_type' => 'fixed']);

        $this->assertNull(app(DynamicPriceService::class)->priceFor($static));
    }

    private function referenceRoundingSettings(): void
    {
        foreach ([
            'zioto_pricing_round_prices' => ['true', 'boolean'],
            'zioto_pricing_rounding_mode' => ['reference', 'string'],
            'zioto_pricing_round_to' => ['1000', 'integer'],
            'zioto_pricing_silver_round_to' => ['10000', 'integer'],
            'tax_gold_labor' => ['10', 'number'],
            'tax_silver' => ['10', 'number'],
        ] as $key => [$value, $type]) {
            Setting::create(['key' => $key, 'value' => $value, 'type' => $type, 'category' => 'pricing', 'label' => $key]);
        }
    }

    #[DataProvider('referenceSnapshotProducts')]
    public function test_reference_rounding_reproduces_snapshot_across_all_price_outputs(string $metal, int $board, string $weight, float $coefficient, int $expectedRial): void
    {
        Http::preventStrayRequests();
        $this->referenceRoundingSettings();
        $this->seedBoard($metal, $board);
        $product = $this->product([
            'price_board_item' => $metal,
            'weight' => $weight,
            'labor_coefficients' => ['nightly' => ['basic' => $coefficient]],
        ]);
        $service = app(DynamicPriceService::class);

        $this->assertSame((float) $expectedRial, $service->priceFor($product, timePeriod: 'nightly', roleSlug: 'basic'));
        $this->assertSame($expectedRial / 10.0, $service->detailsFor($product, 'basic', 'nightly')['calculated_price']);
        $this->assertSame($expectedRial / 10.0, $service->previewMatrix($product, [['slug' => 'nightly']], [['slug' => 'basic']])['nightly']['basic']['final_price']);
        Http::assertNothingSent();
    }

    public static function referenceSnapshotProducts(): array
    {
        return [
            'zioto-gold-bar-0.5gram-995' => ['Gold995_Sell', 347694240, '0.5', 0.075000, 188190000],
            'zioto-gold-bar-1gram-995' => ['Gold995_Sell', 347694240, '1', 0.047000, 365680000],
            'zioto-gold-bar-2.5gram-995' => ['Gold995_Sell', 347694240, '2.5', 0.052000, 918960000],
            'zioto-gold-bar-5gram-995' => ['Gold995_Sell', 347694240, '5', 0.041000, 1816880000],
            'zioto-gold-bar-10gram-995' => ['Gold995_Sell', 347694240, '10', 0.027000, 3580210000],
            'zioto-gold-bar-20gram-995' => ['Gold995_Sell', 347694240, '20', 0.026000, 7152770000],
            'zioto-gold-bar-1oz-995' => ['Gold995_Sell', 347694240, '31.1', 0.023000, 11086870000],
            'zioto-gold-bar-50gram-995' => ['Gold995_Sell', 347694240, '50', 0.015000, 17671560000],
            'zioto-gold-bar-100gram-995' => ['Gold995_Sell', 347694240, '100', 0.011000, 35190140000],
            'zioto-gold-bar-250gram-995' => ['Gold995_Sell', 347694240, '250', 0.013000, 88166570000],
            'zioto-gold-bar-500gram-995' => ['Gold995_Sell', 347694240, '500', 0.012000, 176141910000],
            'zioto-gold-bar-1kilogram-995' => ['Gold995_Sell', 347694240, '1000', 0.012000, 352283810000],
            'ziotoPlus-gold-bar-1gram-9999' => ['Gold9999_Sell', 350837472, '1', 0.074000, 379400000],
            'ziotoPlus-gold-bar-5gram-9999' => ['Gold9999_Sell', 350837472, '5', 0.053000, 1856460000],
            'ziotoPlus-gold-bar-10gram-9999' => ['Gold9999_Sell', 350837472, '10', 0.042000, 3670470000],
            'zioto-silver-bar-2.5gram' => ['Silver9999_Sell', 5070000, '2.5', 0.380000, 19250000],
            'zioto-silver-bar-5gram' => ['Silver9999_Sell', 5070000, '5', 0.340000, 37400000],
            'zioto-silver-bar-10gram' => ['Silver9999_Sell', 5070000, '10', 0.216000, 67870000],
            'zioto-silver-bar-15gram' => ['Silver9999_Sell', 5070000, '15', 0.192000, 99770000],
            'zioto-silver-bar-1oz' => ['Silver9999_Sell', 5070000, '31.1', 0.106000, 191840000],
            'zioto-silver-bar-50gram' => ['Silver9999_Sell', 5070000, '50', 0.066000, 297330000],
            'zioto-silver-bar-100gram' => ['Silver9999_Sell', 5070000, '100', 0.053000, 587290000],
            'zioto-silver-bar-250gram' => ['Silver9999_Sell', 5070000, '250', 0.048000, 1461240000],
            'zioto-silver-bar-500gram' => ['Silver9999_Sell', 5070000, '500', 0.046000, 2916870000],
            'zioto-silver-bar-1kilogram' => ['Silver9999_Sell', 5070000, '1000', 0.024000, 5710870000],
            'zioto-silver-granules_25gram' => ['Silver9999_Sell', 5070000, '25', 0.038000, 144760000],
            'zioto-silver-granules_50gram' => ['Silver9999_Sell', 5070000, '50', 0.037000, 289190000],
            'zioto-silver-granules_100gram' => ['Silver9999_Sell', 5070000, '100', 0.032000, 575630000],
            'labubu-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'simon-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'bluey-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'peppa-pig-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'hellokitty-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'dora-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'lego-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'ice-age-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'spangebob-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'cars-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'paw-patrol-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
            'minecraft-2.5g' => ['Silver9999_Sell', 5070000, '2.5', 0.396000, 19470000],
        ];
    }

    public function test_reference_silver_tax_uses_rounded_subtotal_and_custom_step(): void
    {
        Http::preventStrayRequests();
        $this->referenceRoundingSettings();
        Setting::where('key', 'zioto_pricing_silver_round_to')->first()->update(['value' => '20000']);
        $this->seedBoard('Silver9999_Sell', 5070000);
        $product = $this->product(['price_board_item' => 'Silver9999_Sell', 'weight' => '2.5', 'labor_coefficients' => ['daily' => ['basic' => 0.396]]]);

        $details = app(DynamicPriceService::class)->detailsFor($product, 'basic', 'daily');

        $this->assertSame(1958000.0, $details['calculated_price']);
        $this->assertSame(178000.0, $details['taxes'][0]['amount']);
        Http::assertNothingSent();
    }

    public function test_reference_rounding_does_not_raise_an_exact_boundary(): void
    {
        Http::preventStrayRequests();
        $this->referenceRoundingSettings();
        $this->seedBoard('Silver9999_Sell', 100000);
        $product = $this->product(['price_board_item' => 'Silver9999_Sell', 'weight' => '1', 'labor_coefficients' => ['daily' => ['basic' => 0]]]);

        $this->assertSame(110000.0, app(DynamicPriceService::class)->priceFor($product, timePeriod: 'daily', roleSlug: 'basic'));
        Http::assertNothingSent();
    }

    public function test_reference_mode_respects_disabled_rounding(): void
    {
        Http::preventStrayRequests();
        $this->referenceRoundingSettings();
        Setting::where('key', 'zioto_pricing_round_prices')->first()->update(['value' => 'false']);
        $this->seedBoard('Silver9999_Sell', 5070000);
        $product = $this->product(['price_board_item' => 'Silver9999_Sell', 'weight' => '2.5', 'labor_coefficients' => ['daily' => ['basic' => 0.396]]]);

        $this->assertSame(19463730.0, app(DynamicPriceService::class)->priceFor($product, timePeriod: 'daily', roleSlug: 'basic'));
        Http::assertNothingSent();
    }

    public function test_reference_rounding_accepts_small_decimal_weights_coefficients_and_tax_rates(): void
    {
        Http::preventStrayRequests();
        $this->referenceRoundingSettings();
        Setting::where('key', 'tax_gold_labor')->first()->update(['value' => '0.00001']);
        Setting::where('key', 'tax_silver')->first()->update(['value' => '0.00001']);
        $this->seedBoard('Gold995_Sell', 347694240);
        $gold = $this->product(['price_board_item' => 'Gold995_Sell', 'weight' => '0.0000001', 'labor_coefficients' => ['daily' => ['basic' => 0.00001]]]);
        $service = app(DynamicPriceService::class);

        $this->assertSame(10000.0, $service->priceFor($gold, timePeriod: 'daily', roleSlug: 'basic'));
        $this->assertSame(1000.0, $service->detailsFor($gold, 'basic', 'daily')['calculated_price']);
        $this->seedBoard('Silver9999_Sell', 5070000);
        $silver = $this->product(['price_board_item' => 'Silver9999_Sell', 'weight' => '0.0000001', 'labor_coefficients' => ['daily' => ['basic' => 0.00001]]]);
        $matrix = app(DynamicPriceService::class)->previewMatrix($silver, [['slug' => 'daily']], [['slug' => 'basic']]);
        $this->assertSame(10000.001, $matrix['daily']['basic']['final_price']);
        Http::assertNothingSent();
    }
}
