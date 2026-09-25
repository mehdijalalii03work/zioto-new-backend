<?php

namespace App\Services\Pricing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches precious metal prices from PersianAPI + Tala.ir and builds the board.
 *
 * Port of WordPress ZiotoPricing\API_Client.
 */
class ApiClientService
{
    private array $debugRows = [];

    private array $rawPersianData = [];

    private array $rawTalaData = [];

    private array $sourceStatus = [];

    private bool $usedFallback = false;

    private const PERSIAN_KEY_MAP = [
        137119 => 'Gold705',
        137120 => 'Gold750',
        137121 => 'Gold999',
        684758 => 'Silver999',
    ];

    private const NAME_MAP = [
        'Gold995' => 'طلای ۹۹۵ (۲۴ عیار)',
        'Gold999' => 'طلای ۹۹۹ (۲۴ عیار)',
        'Gold9999' => 'طلای ۹۹۹.۹ (۲۴ عیار)',
        'Gold750' => 'طلای ۷۵۰ (۱۸ عیار)',
        'Gold705' => 'طلای ۷۰۵ (مظنه)',
        'Silver9999' => 'نقره ۹۹۹.۹',
        'Silver925' => 'نقره ۹۲۵',
        'Silver999' => 'نقره ۹۹۹ (۹۹.۹٪)',
        'Silver990' => 'نقره ۹۹۰ (۹۹٪)',
    ];

    /**
     * Get all prices with caching, trends, and source status.
     *
     * @return array{prices: array, debug: array, source_status: array, raw: array{persian: array, tala: array}, used_fallback: bool}
     */
    public function getAllPrices(bool $forceRefresh = false): array
    {
        $stalePolicy = $this->stalePolicy();
        $lastSuccessTime = PricingSettings::lastSuccessfulTime();

        if (! $forceRefresh) {
            $cached = $this->readCache();
            $isStale = $this->isStale($lastSuccessTime, $stalePolicy['max_age']);

            if ($cached !== null && ! ($stalePolicy['block'] && $isStale)) {
                return $cached;
            }
        }

        $prices = $this->fetchFromApi();
        $prices = $this->calculateTrends($prices);

        $payload = [
            'prices' => $prices,
            'debug' => $this->debugRows,
            'source_status' => $this->sourceStatus,
            'raw' => [
                'persian' => $this->rawPersianData,
                'tala' => $this->rawTalaData,
            ],
            'used_fallback' => $this->usedFallback,
        ];

        $ttl = max(10, PricingSettings::cacheDurationSeconds());
        Cache::put('zioto:prices', $prices, $ttl);
        Cache::put('zioto:debug', $this->debugRows, $ttl);
        Cache::put('zioto:source_status', $this->sourceStatus, $ttl);
        Cache::put('zioto:raw', $payload['raw'], $ttl);
        Cache::put('zioto:payload', $payload, $ttl);
        Cache::put('priceboard:prices', $payload, $ttl);
        Cache::put('priceboard:last_sync_at', now(), $ttl);

        if (! $this->usedFallback && $prices !== []) {
            PricingSettings::storeLastSuccessfulData($prices);
        }

        return $payload;
    }

    /**
     * Read cached payload if present.
     */
    public function readCache(): ?array
    {
        $payload = Cache::get('zioto:payload') ?? Cache::get('priceboard:prices');

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        // Legacy Tokeniko shape — treat as miss.
        if (isset($payload['products'])) {
            return null;
        }

        if (! isset($payload['prices'])) {
            return null;
        }

        $payload['debug'] ??= Cache::get('zioto:debug', []);
        $payload['source_status'] ??= Cache::get('zioto:source_status', []);
        $payload['raw'] ??= Cache::get('zioto:raw', ['persian' => [], 'tala' => []]);
        $payload['used_fallback'] ??= false;

        return $payload;
    }

    public function getDebugRows(): array
    {
        $payload = $this->readCache();

        return $payload['debug'] ?? [];
    }

    public function getSourceStatus(): array
    {
        $payload = $this->readCache();

        return $payload['source_status'] ?? [];
    }

    public function getBasePrices(): array
    {
        $payload = $this->readCache();

        return $payload['raw'] ?? ['persian' => [], 'tala' => []];
    }

    public function refresh(): array
    {
        Cache::forget('zioto:payload');
        Cache::forget('zioto:prices');
        Cache::forget('zioto:debug');
        Cache::forget('zioto:source_status');
        Cache::forget('zioto:raw');
        Cache::forget('priceboard:prices');
        Cache::forget('priceboard:last_sync_at');

        return $this->getAllPrices(forceRefresh: true);
    }

    private function fetchFromApi(): array
    {
        $persianData = [];
        $talaData = [];
        $this->usedFallback = false;
        $this->sourceStatus = [];
        $this->debugRows = [];
        $stalePolicy = $this->stalePolicy();

        $persianEnabled = (bool) PricingSettings::get('persian_api_enabled', config('zioto-pricing.persian_api.enabled', true));
        if ($persianEnabled) {
            $persianData = $this->fetchPersianApi();
        } else {
            $this->setSourceStatus('persian', 'disabled', null, false);
        }

        $talaEnabled = (bool) PricingSettings::get('tala_api_enabled', config('zioto-pricing.tala_api.enabled', true));
        if ($talaEnabled) {
            $talaData = $this->fetchTalaApi();
        } else {
            $this->setSourceStatus('tala', 'disabled', null, false);
        }

        $persianData = $this->maybeUseRawFallback('persian', $persianData, $persianEnabled, $stalePolicy);
        $talaData = $this->maybeUseRawFallback('tala', $talaData, $talaEnabled, $stalePolicy);

        if ($persianData === [] && $talaData === []) {
            $this->usedFallback = true;
        }

        $this->rawPersianData = $persianData;
        $this->rawTalaData = $talaData;

        $prices = $this->buildPrices($persianData, $talaData);

        if ($prices === []) {
            Log::error('[ZiotoPricing] No price data after fetching sources.');
            $this->usedFallback = true;

            return $this->getFallbackPrices($stalePolicy);
        }

        return $prices;
    }

    /**
     * @param  array<string, string>  $persianData
     * @param  array<string, string>  $talaData
     * @return array<string, array>
     */
    private function buildPrices(array $persianData, array $talaData): array
    {
        $bc = BcmathHelper::class;
        $nameMap = self::NAME_MAP;

        $manualGold750 = PricingSettings::manualGold750Sell();
        $manualSilver9999 = PricingSettings::manualSilver9999Sell();

        // Gold 750
        $persianGold750 = $persianData['Gold750'] ?? null;

        $talaGold750 = (isset($talaData['Gold750']) && $bc::isPositive($talaData['Gold750']))
            ? $talaData['Gold750']
            : null;
        $coefGold750To995 = PricingSettings::coefficient('coef_gold750_to_gold995');
        $coefGold995To9999 = PricingSettings::coefficient('coef_gold995_to_gold9999');
        $coefBuy = PricingSettings::coefficient('coef_buy_price');
        $coefSilver999To9999 = PricingSettings::coefficient('coef_silver999_to_silver9999');
        $coefSilver9999To925Sell = PricingSettings::coefficient('coef_silver9999_to_silver925_sell');
        $coefSilver9999To925Buy = PricingSettings::coefficient('coef_silver9999_to_silver925_buy');

        $maxApiGold750 = null;
        if ($bc::isPositive($persianGold750) && $bc::isPositive($talaGold750)) {
            $maxApiGold750 = $bc::max($persianGold750, $talaGold750);
        } elseif ($bc::isPositive($persianGold750)) {
            $maxApiGold750 = $persianGold750;
        } elseif ($bc::isPositive($talaGold750)) {
            $maxApiGold750 = $talaGold750;
        }

        $gold750Sell = null;
        if ($manualGold750 !== '' && $bc::isPositive($manualGold750)) {
            if ($bc::isPositive($maxApiGold750) && $bc::comp($manualGold750, $maxApiGold750) > 0) {
                $gold750Sell = $manualGold750;
            } elseif ($bc::isPositive($maxApiGold750)) {
                $gold750Sell = $maxApiGold750;
            } else {
                $gold750Sell = $manualGold750;
            }
        } else {
            $gold750Sell = $maxApiGold750;
        }

        $minApiGold750 = null;
        $gold750ApisEqual = false;
        if ($bc::isPositive($persianGold750) && $bc::isPositive($talaGold750)) {
            if ($bc::comp($persianGold750, $talaGold750) === 0) {
                $minApiGold750 = $persianGold750;
                $gold750ApisEqual = true;
            } else {
                $minApiGold750 = $bc::min($persianGold750, $talaGold750);
            }
        } elseif ($bc::isPositive($persianGold750)) {
            $minApiGold750 = $persianGold750;
        } elseif ($bc::isPositive($talaGold750)) {
            $minApiGold750 = $talaGold750;
        }

        $gold750Buy = null;
        if ($manualGold750 !== '' && $bc::isPositive($manualGold750) && $bc::isPositive($maxApiGold750) && $bc::comp($manualGold750, $maxApiGold750) > 0) {
            $gold750Buy = $bc::mul($gold750Sell, $coefBuy);
        } elseif ($gold750ApisEqual && $bc::isPositive($gold750Sell)) {
            $gold750Buy = $bc::mul($gold750Sell, $coefBuy);
        } elseif ($bc::isPositive($minApiGold750)) {
            $gold750Buy = $minApiGold750;
        } elseif ($bc::isPositive($gold750Sell)) {
            $gold750Buy = $bc::mul($gold750Sell, $coefBuy);
        }

        // Gold 995 / 9999
        $gold995Sell = $bc::isPositive($gold750Sell) ? $bc::mul($gold750Sell, $coefGold750To995) : null;
        $gold995Buy = $bc::isPositive($gold750Buy) ? $bc::mul($gold750Buy, $coefGold750To995) : null;
        $gold9999Sell = $bc::isPositive($gold995Sell) ? $bc::mul($gold995Sell, $coefGold995To9999) : null;
        $gold9999Buy = $bc::isPositive($gold995Buy) ? $bc::mul($gold995Buy, $coefGold995To9999) : null;

        // Silver 9999
        $silver999Persian = $persianData['Silver999'] ?? null;
        $silver9999Persian = $bc::isPositive($silver999Persian)
            ? $bc::mul($silver999Persian, $coefSilver999To9999)
            : null;

        $silver9999Sell = null;
        if ($manualSilver9999 !== '' && $bc::isPositive($manualSilver9999)) {
            if ($bc::isPositive($silver9999Persian) && $bc::comp($manualSilver9999, $silver9999Persian) > 0) {
                $silver9999Sell = $manualSilver9999;
            } elseif ($bc::isPositive($silver9999Persian)) {
                $silver9999Sell = $silver9999Persian;
            } else {
                $silver9999Sell = $manualSilver9999;
            }
        } else {
            $silver9999Sell = $silver9999Persian;
        }

        $silver9999Buy = $bc::isPositive($silver9999Sell) ? $bc::mul($silver9999Sell, $coefBuy) : null;

        // Silver 925
        $silver925Sell = $bc::isPositive($silver9999Sell) ? $bc::mul($silver9999Sell, $coefSilver9999To925Sell) : null;
        $silver925Buy = $bc::isPositive($silver925Sell) ? $bc::mul($silver925Sell, $coefSilver9999To925Buy) : null;

        $prices = [];
        $this->maybeAdd($prices, 'Gold750_Sell', 'Gold750', $gold750Sell, 'sell', $nameMap);
        $this->maybeAdd($prices, 'Gold750_Buy', 'Gold750', $gold750Buy, 'buy', $nameMap);
        $this->maybeAdd($prices, 'Gold995_Sell', 'Gold995', $gold995Sell, 'sell', $nameMap);
        $this->maybeAdd($prices, 'Gold995_Buy', 'Gold995', $gold995Buy, 'buy', $nameMap);
        $this->maybeAdd($prices, 'Gold9999_Sell', 'Gold9999', $gold9999Sell, 'sell', $nameMap);
        $this->maybeAdd($prices, 'Gold9999_Buy', 'Gold9999', $gold9999Buy, 'buy', $nameMap);
        $this->maybeAdd($prices, 'Silver9999_Sell', 'Silver9999', $silver9999Sell, 'sell', $nameMap);
        $this->maybeAdd($prices, 'Silver9999_Buy', 'Silver9999', $silver9999Buy, 'buy', $nameMap);
        $this->maybeAdd($prices, 'Silver925_Sell', 'Silver925', $silver925Sell, 'sell', $nameMap);
        $this->maybeAdd($prices, 'Silver925_Buy', 'Silver925', $silver925Buy, 'buy', $nameMap);

        // Debug rows
        $this->debugRows['Gold750_Sell'] = $this->debugRow('Gold750_Sell', $persianGold750, $talaGold750, $manualGold750 !== '' ? $manualGold750 : null, $gold750Sell, 'geram18k (Tala.ir)', 'Max(PersianAPI, Tala.ir) or Manual if > Max', $nameMap);
        $this->debugRows['Gold750_Buy'] = $this->debugRow('Gold750_Buy', $persianGold750, $talaGold750, $manualGold750 !== '' ? $manualGold750 : null, $gold750Buy, 'Min(APIs) or Sell*0.99', 'Min(PersianAPI, Tala.ir) or Sell×0.99 if Manual>Max', $nameMap);
        $this->debugRows['Gold995_Sell'] = $this->debugRow('Gold995_Sell', null, null, null, $gold995Sell, 'Gold750_Sell * 1.3333', 'Gold750_Sell * 1.3333', $nameMap);
        $this->debugRows['Gold995_Buy'] = $this->debugRow('Gold995_Buy', null, null, null, $gold995Buy, 'Gold750_Buy * 1.3333', 'Gold750_Buy * 1.3333', $nameMap);
        $this->debugRows['Gold9999_Sell'] = $this->debugRow('Gold9999_Sell', null, null, null, $gold9999Sell, 'Gold995_Sell * 1.005', 'Gold995_Sell * 1.005', $nameMap);
        $this->debugRows['Gold9999_Buy'] = $this->debugRow('Gold9999_Buy', null, null, null, $gold9999Buy, 'Gold995_Buy * 1.005', 'Gold995_Buy * 1.005', $nameMap);
        $this->debugRows['Silver9999_Sell'] = $this->debugRow('Silver9999_Sell', $silver999Persian, null, $manualSilver9999 !== '' ? $manualSilver9999 : null, $silver9999Sell, 'Silver999 * 1.04', 'PersianAPI Silver999 × 1.04, then Max(result, Manual)', $nameMap);
        $this->debugRows['Silver9999_Buy'] = $this->debugRow('Silver9999_Buy', null, null, null, $silver9999Buy, 'Silver9999_Sell * 0.99', 'Silver9999_Sell × 0.99', $nameMap);
        $this->debugRows['Silver925_Sell'] = $this->debugRow('Silver925_Sell', $silver9999Sell, null, null, $silver925Sell, 'Silver9999_Sell * '.$coefSilver9999To925Sell, 'Silver9999_Sell × coefficient', $nameMap);
        $this->debugRows['Silver925_Buy'] = $this->debugRow('Silver925_Buy', $silver925Sell, null, null, $silver925Buy, 'Silver925_Sell * '.$coefSilver9999To925Buy, 'Silver925_Sell × coefficient', $nameMap);

        return $prices;
    }

    private function maybeAdd(array &$prices, string $key, string $baseKey, mixed $value, string $priceType, array $nameMap): void
    {
        if ($value === null || ! BcmathHelper::isPositive($value)) {
            return;
        }

        $baseName = $nameMap[$baseKey] ?? $baseKey;
        $label = $priceType === 'sell'
            ? sprintf('قیمت فروش %s', $baseName)
            : sprintf('قیمت خرید %s', $baseName);

        $prices[$key] = [
            'name' => $label,
            'value' => BcmathHelper::toFloat((string) $value),
            'price_type' => $priceType,
            'base_metal' => $baseKey,
            'trend' => null,
            'updated_at' => now()->timestamp,
        ];
    }

    private function debugRow(string $key, mixed $persian, mixed $tala, mixed $manual, mixed $selected, string $coefficient, string $description, array $nameMap): array
    {
        $format = function ($value) {
            if ($value === null || ! is_numeric($value) || (float) $value <= 0) {
                return null;
            }

            return (int) round((float) $value);
        };

        $baseKey = preg_replace('/_(Sell|Buy)$/', '', $key);
        $baseName = $nameMap[$baseKey] ?? $baseKey;

        if (str_contains($key, '_Sell')) {
            $displayName = sprintf('قیمت فروش %s', $baseName);
        } elseif (str_contains($key, '_Buy')) {
            $displayName = sprintf('قیمت خرید %s', $baseName);
        } else {
            $displayName = $nameMap[$key] ?? $key;
        }

        return [
            'key' => $key,
            'name' => $displayName,
            'persian' => $format($persian),
            'tala' => $format($tala),
            'manual' => $format($manual),
            'selected' => $format($selected),
            'coefficient' => $coefficient,
            'description' => $description,
        ];
    }

    private function fetchPersianApi(): array
    {
        $url = (string) config('zioto-pricing.persian_api.url');
        $token = trim((string) config('zioto-pricing.persian_api.token', ''));

        try {
            $pending = Http::timeout(15)
                ->retry(2, 200, throw: false)
                ->withHeaders(['Accept' => 'application/json'])
                ->when($token !== '', fn ($http) => $http->withToken($token))
                ->get($url);
        } catch (ConnectionException $e) {
            Log::error('[ZiotoPricing] PersianAPI connection error: '.$e->getMessage());

            return [];
        }

        if ($pending->failed()) {
            Log::error('[ZiotoPricing] PersianAPI HTTP error: '.$pending->status());

            return [];
        }

        $data = $pending->json();

        if (empty($data['result']) || ! is_array($data['result'])) {
            Log::error('[ZiotoPricing] PersianAPI invalid response format');

            return [];
        }

        return $this->mapPersianItems($data['result']);
    }

    private function fetchTalaApi(): array
    {
        $token = trim((string) config('zioto-pricing.tala_api.token', ''));
        $cookie = trim((string) config('zioto-pricing.tala_api.cookie', ''));

        if ($token === '') {
            return [];
        }

        $base = rtrim((string) config('zioto-pricing.tala_api.base_url'), '/');
        $keys = trim((string) config('zioto-pricing.tala_api.keys', ''));
        $url = $base.($keys !== '' ? '/'.$keys : '').'?'.http_build_query(['token' => $token]);

        try {
            $pending = Http::timeout(15)
                ->retry(2, 200, throw: false)
                ->withoutVerifying()
                ->withHeaders(['Accept' => 'application/json'])
                ->when($cookie !== '', fn ($http) => $http->withHeaders(['Cookie' => $cookie]))
                ->get($url);
        } catch (ConnectionException $e) {
            Log::error('[ZiotoPricing] Tala API connection error: '.$e->getMessage());

            return [];
        }

        if ($pending->failed()) {
            Log::error('[ZiotoPricing] Tala API HTTP error: '.$pending->status());

            return [];
        }

        $data = $pending->json();

        if (empty($data) || ! is_array($data)) {
            Log::error('[ZiotoPricing] Tala API invalid response format');

            return [];
        }

        if (isset($data['success']) && $data['success'] !== true) {
            Log::error('[ZiotoPricing] Tala API error: '.json_encode($data['message'] ?? $data));

            return [];
        }

        $rates = $data['rates'] ?? $data;

        if (isset($rates[0]) && is_array($rates[0]) && isset($rates[0]['key'])) {
            $indexed = [];
            foreach ($rates as $rate) {
                if (! isset($rate['key'], $rate['value'])) {
                    continue;
                }

                $indexed[(string) $rate['key']] = $rate;
            }
            $rates = $indexed;
        }

        return $this->mapTalaItems($rates);
    }

    /**
     * @param  list<array>  $items
     * @return array<string, string>
     */
    private function mapPersianItems(array $items): array
    {
        $mapped = [];

        foreach ($items as $item) {
            if (! isset($item['key'], $item['price'])) {
                continue;
            }

            $itemKey = (int) $item['key'];
            if (! isset(self::PERSIAN_KEY_MAP[$itemKey])) {
                continue;
            }

            $targetKey = self::PERSIAN_KEY_MAP[$itemKey];
            $finalPrice = BcmathHelper::div((string) $item['price'], '10');

            if (isset($mapped[$targetKey])) {
                $mapped[$targetKey] = BcmathHelper::max($mapped[$targetKey], $finalPrice);
            } else {
                $mapped[$targetKey] = $finalPrice;
            }
        }

        return $mapped;
    }

    /**
     * @return array<string, string>
     */
    private function mapTalaItems(array $data): array
    {
        $mapped = [];

        if (isset($data['geram18k']['value'])) {
            $mapped['Gold750'] = (string) $data['geram18k']['value'];
        }

        if (isset($data['bazartehran']['value'])) {
            $mapped['Gold705'] = (string) $data['bazartehran']['value'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $currentPrices
     * @return array<string, mixed>
     */
    private function calculateTrends(array $currentPrices): array
    {
        $previousPrices = PricingSettings::previousPrices();
        $threshold = PricingSettings::trendThreshold();

        foreach ($currentPrices as $key => &$priceData) {
            if (! isset($previousPrices[$key])) {
                continue;
            }

            $prevValue = (float) $previousPrices[$key];
            $currValue = (float) $priceData['value'];
            $diff = abs($currValue - $prevValue);

            if ($diff <= $threshold) {
                $priceData['trend'] = 'stable';
            } elseif ($currValue > $prevValue) {
                $priceData['trend'] = 'up';
            } else {
                $priceData['trend'] = 'down';
            }
        }
        unset($priceData);

        $valuesOnly = array_map(fn ($price) => $price['value'], $currentPrices);
        PricingSettings::storePreviousPrices($valuesOnly);

        return $currentPrices;
    }

    /**
     * @return array{max_age:int, block:bool}
     */
    private function stalePolicy(): array
    {
        return [
            'max_age' => PricingSettings::staleMaxAgeSeconds(),
            'block' => PricingSettings::staleBlock(),
        ];
    }

    private function isStale(?int $timestamp, int $maxAge): bool
    {
        if ($timestamp === null || $timestamp <= 0 || $maxAge <= 0) {
            return false;
        }

        return (now()->timestamp - $timestamp) > $maxAge;
    }

    private function setSourceStatus(string $source, string $status, ?int $timestamp, bool $usedFallback): void
    {
        $age = $timestamp !== null ? now()->timestamp - $timestamp : null;

        $this->sourceStatus[$source] = [
            'status' => $status,
            'used_fallback' => $usedFallback,
            'last_time' => $timestamp,
            'age' => $age,
        ];
    }

    private function maybeUseRawFallback(string $source, array $data, bool $enabled, array $stalePolicy): array
    {
        if (! $enabled) {
            return [];
        }

        $lastRaw = PricingSettings::lastSuccessfulRaw();
        $lastTimes = PricingSettings::lastSuccessfulRawTimes();
        $rawData = (array) ($lastRaw[$source] ?? []);
        $rawTime = isset($lastTimes[$source]) ? (int) $lastTimes[$source] : null;

        if ($data !== []) {
            PricingSettings::storeLastSuccessfulRaw($source, $data);
            $this->setSourceStatus($source, 'ok', now()->timestamp, false);

            return $data;
        }

        $isStale = $this->isStale($rawTime, $stalePolicy['max_age']);

        if ($rawData !== [] && ! ($stalePolicy['block'] && $isStale)) {
            $this->usedFallback = true;
            $this->setSourceStatus($source, 'fallback', $rawTime, true);

            return $rawData;
        }

        $this->setSourceStatus($source, $isStale ? 'stale' : 'down', $rawTime, false);

        return [];
    }

    /**
     * @return array<string, array>
     */
    private function getFallbackPrices(array $stalePolicy): array
    {
        $lastSuccessTime = PricingSettings::lastSuccessfulTime();

        if ($stalePolicy['block'] && $this->isStale($lastSuccessTime, $stalePolicy['max_age'])) {
            return [];
        }

        $fallback = PricingSettings::lastSuccessfulData();

        return is_array($fallback) ? $fallback : [];
    }
}
