<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed access to Zioto pricing settings with plugin-parity defaults.
 */
class PricingSettings
{
    public const DEFAULT_LABOR_ROLES = [
        ['slug' => 'basic', 'name' => 'مشتری سطح پایه', 'is_default' => true],
        ['slug' => 'pro', 'name' => 'مشتری سطح پرو'],
        ['slug' => 'premium', 'name' => 'مشتری سطح پریمیوم'],
        ['slug' => 'luxury', 'name' => 'مشتری سطح لاکچری'],
    ];

    public const DEFAULT_TIME_PERIODS = [
        ['slug' => 'daily', 'name' => 'روزانه', 'start' => '11:00', 'end' => '18:00'],
        ['slug' => 'nightly', 'name' => 'شبانه', 'start' => '18:00', 'end' => '11:00'],
    ];

    public const COEFFICIENT_DEFAULTS = [
        'coef_gold999_to_gold995' => '0.995996',
        'coef_gold999_to_gold9999' => '1.005',
        'coef_gold995_sell_ratio' => '1',
        'coef_gold995_buy_ratio' => '0.995',
        'coef_gold9999_sell_ratio' => '1',
        'coef_gold9999_buy_ratio' => '0.995',
        'coef_buy_price' => '0.99',
        'coef_gold999_sell_ratio' => '1.001',
        'coef_gold999_buy_ratio' => '0.997',
        'coef_silver9999_to_silver999' => '0.961538',
        'coef_silver_buy_price' => '0.99',
        'coef_gold750_sell_ratio' => '1.001',
        'coef_gold750_buy_ratio' => '0.997',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue("zioto_pricing_{$key}", $default);
    }

    public static function coefficient(string $key): string
    {
        return (string) self::get($key, self::COEFFICIENT_DEFAULTS[$key] ?? '1');
    }

    public static function manualGold750Sell(): string
    {
        return (string) (self::get('manual_gold750_sell', '') ?? '');
    }

    public static function manualGold999Sell(): string
    {
        return (string) (self::get('manual_gold999_sell', '') ?? '');
    }

    public static function manualSilver9999Sell(): string
    {
        return (string) (self::get('manual_silver9999_sell', '') ?? '');
    }

    public static function enableDynamicPricing(): bool
    {
        return (bool) self::get('enable_dynamic_pricing', true);
    }

    public static function roundPrices(): bool
    {
        return (bool) self::get('round_prices', false);
    }

    public static function roundTo(): int
    {
        return (int) self::get('round_to', 1000);
    }

    /**
     * VAT charged on the labor (making) fee of gold products, in percent.
     *
     * Kept unprefixed like the other tax keys (`tax_gold`, `tax_silver`) and
     * only applied to dynamic (price board) products.
     */
    public static function taxGoldLabor(): float
    {
        return (float) Setting::getValue('tax_gold_labor', 0);
    }

    public static function trendThreshold(): int
    {
        return max(1, (int) self::get('trend_threshold', 100));
    }

    public static function cacheDurationSeconds(): int
    {
        return max(10, (int) self::get('cache_duration_seconds', 300));
    }

    public static function staleMaxAgeSeconds(): int
    {
        return max(0, (int) self::get('stale_max_age_seconds', 21600));
    }

    public static function staleBlock(): bool
    {
        return (bool) self::get('stale_block', false);
    }

    /** @return list<string> */
    public static function metalCategories(): array
    {
        return array_values((array) self::get('metal_categories', []));
    }

    /** @return list<int> */
    public static function disabledProducts(): array
    {
        return array_map('intval', (array) self::get('disabled_products', []));
    }

    /** @return list<int> */
    public static function disabledCategories(): array
    {
        return array_map('intval', (array) self::get('disabled_categories', []));
    }

    public static function enableSalesHours(): bool
    {
        return (bool) self::get('enable_sales_hours', false);
    }

    public static function salesStartTime(): string
    {
        return (string) self::get('sales_start_time', '09:00');
    }

    public static function salesEndTime(): string
    {
        return (string) self::get('sales_end_time', '23:00');
    }

    public static function showDiscountBadge(): bool
    {
        return (bool) self::get('show_discount', false);
    }

    /** @return list<array{slug:string,name:string,is_default?:bool}> */
    public static function laborRoles(): array
    {
        $roles = self::get('labor_roles', null);

        if (! is_array($roles) || $roles === []) {
            return self::DEFAULT_LABOR_ROLES;
        }

        return $roles;
    }

    /** @return list<array{slug:string,name:string,start:string,end:string}> */
    public static function timePeriods(): array
    {
        $periods = self::get('time_periods', null);

        if (! is_array($periods) || $periods === []) {
            return self::DEFAULT_TIME_PERIODS;
        }

        return $periods;
    }

    public static function previousPrices(): array
    {
        return (array) self::get('previous_prices', []);
    }

    public static function storePreviousPrices(array $values): void
    {
        Setting::updateOrCreate(
            ['key' => 'zioto_pricing_previous_prices'],
            ['value' => json_encode($values), 'type' => 'json', 'category' => 'pricing', 'label' => 'قیمت‌های قبلی ترندها'],
        );
    }

    public static function lastSuccessfulData(): array
    {
        return (array) self::get('last_successful_api_data', []);
    }

    public static function storeLastSuccessfulData(array $prices): void
    {
        Setting::updateOrCreate(
            ['key' => 'zioto_pricing_last_successful_api_data'],
            ['value' => json_encode($prices), 'type' => 'json', 'category' => 'pricing', 'label' => 'آخرین داده موفق API'],
        );
        Setting::updateOrCreate(
            ['key' => 'zioto_pricing_last_successful_api_time'],
            ['value' => (string) now()->timestamp, 'type' => 'integer', 'category' => 'pricing', 'label' => 'زمان آخرین داده موفق'],
        );
    }

    public static function lastSuccessfulTime(): ?int
    {
        $time = self::get('last_successful_api_time', null);

        return $time !== null ? (int) $time : null;
    }

    public static function lastSuccessfulRaw(): array
    {
        return (array) self::get('last_successful_raw_data', []);
    }

    public static function lastSuccessfulRawTimes(): array
    {
        return (array) self::get('last_successful_raw_time', []);
    }

    /**
     * Last time each raw base price actually changed value, keyed by "{source}.{metal}".
     *
     * @return array<string, int>
     */
    public static function lastRawPriceChanges(): array
    {
        return (array) self::get('last_raw_price_changes', []);
    }

    public static function storeLastSuccessfulRaw(string $source, array $data): void
    {
        $allData = self::lastSuccessfulRaw();
        $allTime = self::lastSuccessfulRawTimes();

        $changes = self::detectRawPriceChanges($source, $data, (array) ($allData[$source] ?? []));

        $allData[$source] = $data;
        $allTime[$source] = now()->timestamp;

        Setting::updateOrCreate(
            ['key' => 'zioto_pricing_last_successful_raw_data'],
            ['value' => json_encode($allData), 'type' => 'json', 'category' => 'pricing', 'label' => 'آخرین داده خام موفق'],
        );
        Setting::updateOrCreate(
            ['key' => 'zioto_pricing_last_successful_raw_time'],
            ['value' => json_encode($allTime), 'type' => 'json', 'category' => 'pricing', 'label' => 'زمان آخرین داده خام موفق'],
        );

        if ($changes !== []) {
            $allChanges = self::lastRawPriceChanges();

            foreach ($changes as $path => $time) {
                $allChanges[$path] = $time;
            }

            Setting::updateOrCreate(
                ['key' => 'zioto_pricing_last_raw_price_changes'],
                ['value' => json_encode($allChanges), 'type' => 'json', 'category' => 'pricing', 'label' => 'زمان آخرین تغییر قیمت پایه'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return array<string, int>
     */
    private static function detectRawPriceChanges(string $source, array $current, array $previous): array
    {
        $now = now()->timestamp;
        $changes = [];

        foreach ($current as $key => $value) {
            $newValue = (string) $value;

            if (! array_key_exists($key, $previous)) {
                $changes["{$source}.{$key}"] = $now;

                continue;
            }

            $oldValue = (string) $previous[$key];

            $unchanged = is_numeric($newValue) && is_numeric($oldValue)
                ? bccomp($newValue, $oldValue, 8) === 0
                : $newValue === $oldValue;

            if (! $unchanged) {
                $changes["{$source}.{$key}"] = $now;
            }
        }

        return $changes;
    }

    public static function invalidateBoardCache(): void
    {
        Cache::forget('priceboard:prices');
        Cache::forget('priceboard:last_sync_at');
        Cache::forget('zioto:prices');
        Cache::forget('zioto:debug');
        Cache::forget('zioto:source_status');
        Cache::forget('zioto:raw');
    }
}
