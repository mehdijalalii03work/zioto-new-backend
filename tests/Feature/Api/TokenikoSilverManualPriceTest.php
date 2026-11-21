<?php

namespace Tests\Feature\Api;

use App\Models\PriceAuditLog;
use App\Models\Setting;
use App\Services\PriceBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TokenikoSilverManualPriceTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/tokeniko/pricing/silver9999-manual';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tokeniko.silver_price_secret', 'test-tokeniko-secret');
    }

    private function sendPrice(mixed $price, string $secret = 'test-tokeniko-secret'): TestResponse
    {
        return $this->withHeaders(['X-Tokeniko-Secret' => $secret])
            ->postJson(self::ENDPOINT, ['price_rial' => $price]);
    }

    public function test_valid_price_updates_manual_setting_and_identifies_api_in_audit_log(): void
    {
        Cache::put('zioto:payload', ['prices' => ['Silver9999_Sell' => ['value' => 100]]]);
        Cache::put('priceboard:prices', ['prices' => ['Silver9999_Sell' => ['value' => 100]]]);

        $this->sendPrice(5130001)
            ->assertOk()
            ->assertJsonPath('price_rial', 5130001)
            ->assertJsonPath('updated', true);

        $this->assertDatabaseHas('settings', [
            'key' => 'zioto_pricing_manual_silver9999_sell',
            'value' => '513000.1',
            'type' => 'number',
            'category' => 'pricing',
            'label' => 'manual_silver9999_sell',
        ]);
        $audit = PriceAuditLog::query()->sole();
        $this->assertSame('توکنیکو (API)', $audit->actor_name);
        $this->assertSame('setting', $audit->subject_type);
        $this->assertSame('513000.1', $audit->changes['value']['new']);
        $this->assertNull(Cache::get('zioto:payload'));
        $this->assertNull(Cache::get('priceboard:prices'));
    }

    public function test_repeating_the_same_price_does_not_create_another_audit_log(): void
    {
        $this->sendPrice(5130000)->assertOk()->assertJsonPath('updated', true);

        $this->sendPrice(5130000)->assertOk()->assertJsonPath('updated', false);

        $this->assertSame(1, PriceAuditLog::query()->count());
    }

    public function test_existing_price_change_records_previous_value_and_request_ip(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_manual_silver9999_sell',
            'value' => '500000',
            'type' => 'number',
            'category' => 'pricing',
            'label' => 'manual_silver9999_sell',
        ]);

        $this->sendPrice(5130000)->assertOk()->assertJsonPath('updated', true);

        $audit = PriceAuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('500000', $audit->changes['value']['old']);
        $this->assertSame('513000', $audit->changes['value']['new']);
        $this->assertSame('توکنیکو (API)', $audit->actor_name);
        $this->assertSame('127.0.0.1', $audit->actor_ip);
    }

    public function test_computed_board_reads_new_manual_silver_price_after_cache_invalidation(): void
    {
        Cache::put('zioto:payload', ['prices' => ['Silver9999_Sell' => ['value' => 100]]]);
        Http::preventStrayRequests();
        Http::fake([
            '*persianapi*' => Http::response(['result' => []], 200),
            '*tala.ir*' => Http::response(['success' => true, 'rates' => []], 200),
        ]);

        $this->sendPrice(5130000)->assertOk();
        $board = app(PriceBoardService::class)->getPrices();

        $this->assertSame(5130000.0, $board['prices']['Silver9999_Sell']['value']);
    }

    public function test_invalid_or_missing_secret_cannot_change_the_price(): void
    {
        $this->sendPrice(5130000, 'wrong-secret')->assertUnauthorized();
        $this->postJson(self::ENDPOINT, ['price_rial' => 5130000])->assertUnauthorized();

        $this->assertSame(0, Setting::query()->count());
        $this->assertSame(0, PriceAuditLog::query()->count());
    }

    public function test_unconfigured_secret_rejects_requests(): void
    {
        config()->set('services.tokeniko.silver_price_secret', '');

        $this->sendPrice(5130000)->assertServiceUnavailable();

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_invalid_prices_are_rejected_without_changes(): void
    {
        foreach ([0, -1, 1.5, 'abc', null] as $invalidPrice) {
            $this->sendPrice($invalidPrice)->assertUnprocessable()->assertJsonValidationErrors('price_rial');
        }

        $this->assertSame(0, Setting::query()->count());
        $this->assertSame(0, PriceAuditLog::query()->count());
    }

    public function test_eleventh_price_update_request_within_a_minute_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->sendPrice(5130000 + $attempt)->assertOk();
        }

        $this->sendPrice(5130011)->assertTooManyRequests();

        $this->assertSame(10, PriceAuditLog::query()->count());
    }

    public function test_direct_tokeniko_board_keeps_its_own_silver_price(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_tokeniko_board_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'tokeniko_board_enabled',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            '*tokeniko.com/api/prices-with-change*' => Http::response(['products' => [
                ['name' => 'Silver9999', 'sellPrice' => 7000000, 'buyPrice' => 6900000],
            ]], 200),
        ]);

        $this->sendPrice(5130000)->assertOk();
        $board = app(PriceBoardService::class)->getPrices();

        $this->assertSame(7000000.0, $board['prices']['Silver9999_Sell']['value']);
        $this->assertDatabaseHas('settings', [
            'key' => 'zioto_pricing_manual_silver9999_sell',
            'value' => '513000',
        ]);
    }
}
