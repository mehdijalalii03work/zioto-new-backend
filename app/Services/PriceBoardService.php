<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceBoardService
{
    private const API_URL = 'https://tokeniko.com/api/prices-with-change';

    private const CACHE_KEY = 'priceboard:prices';

    private const LAST_SYNC_KEY = 'priceboard:last_sync_at';

    private const CACHE_TTL = 120;

    private const STALE_WARNING_MINUTES = 5;

    public function fetchAndStore(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        try {
            $response = Http::retry(3, 200, fn (ConnectionException $e) => true)
                ->timeout(10)
                ->withoutVerifying()
                ->get(self::API_URL);

            if ($response->failed()) {
                Log::error('[PriceBoard] API request failed', ['status' => $response->status()]);

                return $this->fallbackToCache($cached);
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::error('[PriceBoard] Invalid API response');

                return $this->fallbackToCache($cached);
            }

            if (isset($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as &$product) {
                    if (isset($product['sellPrice'])) {
                        $product['sellPrice'] = (int) round($product['sellPrice'] / 10);
                    }
                    if (isset($product['buyPrice'])) {
                        $product['buyPrice'] = (int) round($product['buyPrice'] / 10);
                    }
                }
                unset($product);
            }

            Cache::put(self::CACHE_KEY, $data, self::CACHE_TTL);
            Cache::put(self::LAST_SYNC_KEY, now(), self::CACHE_TTL);

            Log::info('[PriceBoard] Prices synced', ['items' => count($data)]);

            return $data;
        } catch (ConnectionException $e) {
            Log::error('[PriceBoard] Connection error after retries: '.$e->getMessage());

            $result = $this->fallbackToCache($cached);
            $this->logIfStale();

            return $result;
        }
    }

    public function getPrices(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public function getLastSyncAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_SYNC_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    private function fallbackToCache(mixed $cached): array
    {
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        return Cache::get(self::CACHE_KEY, []);
    }

    private function logIfStale(): void
    {
        $lastSync = $this->getLastSyncAt();

        if ($lastSync && $lastSync->diffInMinutes() >= self::STALE_WARNING_MINUTES) {
            Log::warning('[PriceBoard] Prices are stale', [
                'last_sync_at' => $lastSync->toDateTimeString(),
                'minutes_ago' => $lastSync->diffInMinutes(),
            ]);
        }
    }
}
