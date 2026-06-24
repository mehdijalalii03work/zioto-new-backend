<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceBoardService
{
    private const API_URL = 'https://tokeniko.com/api/prices-with-change';

    private const CACHE_KEY = 'priceboard:prices';

    private const CACHE_TTL = 120;

    public function fetchAndStore(): array
    {
        $response = Http::timeout(15)->withoutVerifying()->get(self::API_URL);

        if ($response->failed()) {
            Log::error('[PriceBoard] API request failed', ['status' => $response->status()]);

            return Cache::get(self::CACHE_KEY, []);
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::error('[PriceBoard] Invalid API response');

            return Cache::get(self::CACHE_KEY, []);
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

        Log::info('[PriceBoard] Prices synced', ['items' => count($data)]);

        return $data;
    }

    public function getPrices(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }
}
