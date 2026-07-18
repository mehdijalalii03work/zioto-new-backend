<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TapsiShopService
{
    public function sendBatch(array $products): bool
    {
        if (empty($products)) {
            return true;
        }

        $token = $this->getToken();

        if (empty($token)) {
            Log::critical('[TapsiShop] Auth token is missing');

            return false;
        }

        $url = config('tapsi.base_url').'/products';

        $response = Http::withHeaders([
            'Accept' => 'text/plain',
            'Content-Type' => 'application/json',
            'TapsiShop.Hub.Authorization' => $token,
        ])->timeout(25)->put($url, ['products' => $products]);

        if ($response->failed()) {
            Log::error('[TapsiShop] Request failed', ['status' => $response->status()]);

            if ($response->status() === 401) {
                $newToken = $this->refreshToken($token);
                if (! empty($newToken)) {
                    return $this->sendBatch($products);
                }
            }

            return false;
        }

        $body = $response->json();

        if (! empty($body['success'])) {
            Log::info('[TapsiShop] Batch synced', ['count' => count($products)]);

            return true;
        }

        Log::warning('[TapsiShop] API rejected update', ['status' => $response->status()]);

        return false;
    }

    public function calculateTapsiPrice(float $priceInToman): int
    {
        $threshold = config('tapsi.markup_threshold', 50_000_000);
        $belowMarkup = config('tapsi.markup_below_threshold', 2);
        $aboveMarkup = config('tapsi.markup_above_threshold', 1);

        $markup = $priceInToman < $threshold ? $belowMarkup : $aboveMarkup;

        return (int) (round($priceInToman * (1 + $markup / 100)) * 10);
    }

    private function getToken(): string
    {
        $token = config('tapsi.auth_token', '');

        if (! empty($token)) {
            return trim(str_ireplace('Bearer ', '', $token));
        }

        $stored = Setting::getValue('tapsi_shop_outgoing_auth_token', '');

        return trim(str_ireplace('Bearer ', '', $stored));
    }

    private function refreshToken(string $expiredToken): string
    {
        $url = config('tapsi.base_url').'/refresh-token';
        $name = config('tapsi.auth_name', 'zioto_sync_node');

        Log::info('[TapsiShop] Attempting token refresh...');

        $response = Http::withHeaders([
            'Accept' => 'text/plain',
            'Content-Type' => 'application/json',
        ])->timeout(20)->post($url, [
            'token' => $expiredToken,
            'name' => $name,
            'revokeCurrentToken' => false,
        ]);

        if ($response->failed()) {
            Log::critical('[TapsiShop] Token refresh failed');

            return '';
        }

        $body = $response->json();

        if ($response->successful() && ! empty($body['success']) && ! empty($body['data']['token'])) {
            $newToken = trim($body['data']['token']);

            Setting::updateOrCreate(
                ['key' => 'tapsi_shop_outgoing_auth_token'],
                [
                    'value' => $newToken,
                    'type' => 'string',
                    'category' => 'tapsi',
                    'label' => 'Tapsi Shop Outgoing Auth Token',
                ]
            );

            Log::info('[TapsiShop] Token refreshed successfully');

            return $newToken;
        }

        Log::critical('[TapsiShop] Token refresh rejected');

        return '';
    }
}
