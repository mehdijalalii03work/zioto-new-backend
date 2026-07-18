<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenikoShopService
{
    private const API_URL = 'https://apigateway.tokeniko.com/shop/api/Category/getPrices';

    private const CACHE_KEY = 'tokeniko:shop_prices';

    private const CACHE_TTL = 60;

    public function fetchAndStore(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->get(self::API_URL);

            if ($response->failed()) {
                Log::error('[TokenikoShop] API request failed', ['status' => $response->status()]);

                return $cached ?? [];
            }

            $data = $response->json();

            if (! is_array($data) || empty($data['Model'])) {
                Log::error('[TokenikoShop] Invalid API response structure');

                return $cached ?? [];
            }

            $prices = [];

            foreach ($data['Model'] as $item) {
                if (empty($item['Name']) || ! isset($item['SellPrice'])) {
                    continue;
                }

                $key = mb_strtolower(trim($item['Name']));
                $prices[$key] = (string) ((int) round((float) $item['SellPrice']) * 10);
            }

            Cache::put(self::CACHE_KEY, $prices, self::CACHE_TTL);

            Log::info('[TokenikoShop] Prices synced', ['items' => count($prices)]);

            return $prices;
        } catch (\Exception $e) {
            Log::error('[TokenikoShop] Connection error: '.$e->getMessage());

            return $cached ?? [];
        }
    }

    public function getPrices(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public function getPriceByName(string $name): ?string
    {
        $prices = $this->getPrices();
        $key = mb_strtolower(trim($name));

        return $prices[$key] ?? null;
    }
}
