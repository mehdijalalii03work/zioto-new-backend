<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TapsiShopService
{
    private string $token = '';

    public function sendBatch(array $products): bool
    {
        if (empty($products)) {
            return true;
        }

        $this->token = $this->getToken();

        if (empty($this->token)) {
            Log::critical('[TapsiShop] Auth token is missing');

            return false;
        }

        $chunkSize = (int) config('tapsi.chunk_size', 30);
        $delay = (int) config('tapsi.delay_between_chunks', 1);

        $chunks = array_chunk($products, $chunkSize);

        Log::info('[TapsiShop] Splitting products into batches', [
            'total' => count($products),
            'chunks' => count($chunks),
            'chunk_size' => $chunkSize,
        ]);

        $allSuccess = true;

        foreach ($chunks as $index => $chunk) {
            $success = $this->sendChunk($chunk);

            if (! $success) {
                Log::critical('[TapsiShop] Batch sync aborted', [
                    'failed_chunk' => $index + 1,
                    'total_chunks' => count($chunks),
                ]);
                $allSuccess = false;

                break;
            }

            if ($index < count($chunks) - 1 && $delay > 0) {
                sleep($delay);
            }
        }

        return $allSuccess;
    }

    private function sendChunk(array $chunk, int $attempt = 1): bool
    {
        $url = config('tapsi.base_url').'/products';

        $response = Http::withHeaders([
            'Accept' => 'text/plain',
            'Content-Type' => 'application/json',
            'TapsiShop.Hub.Authorization' => $this->token,
        ])->timeout(25)->put($url, ['products' => $chunk]);

        if ($response->failed()) {
            if ($response->status() === 401 && $attempt < 2) {
                Log::info('[TapsiShop] Got 401, refreshing token and retrying batch...');

                $newToken = $this->refreshToken($this->token);

                if (! empty($newToken)) {
                    $this->token = $newToken;

                    return $this->sendChunk($chunk, $attempt + 1);
                }
            }

            Log::error('[TapsiShop] Request failed', ['status' => $response->status()]);

            return false;
        }

        $body = $response->json();

        if (! empty($body['success'])) {
            Log::info('[TapsiShop] Batch synced', ['count' => count($chunk)]);

            return true;
        }

        $errorMsg = $body['messages'][0]['message'] ?? 'Unknown error';
        Log::warning('[TapsiShop] API rejected update', ['status' => $response->status(), 'message' => $errorMsg]);

        return false;
    }

    public function calculateTapsiPrice(float $priceInToman): int
    {
        $threshold = config('tapsi.markup_threshold', 50_000_000);
        $belowMarkup = config('tapsi.markup_below_threshold', 2);
        $aboveMarkup = config('tapsi.markup_above_threshold', 1);

        $markup = $priceInToman < $threshold ? $belowMarkup : $aboveMarkup;

        return (int) round($priceInToman * (1 + $markup / 100));
    }

    private function getToken(): string
    {
        $stored = Setting::getValue('tapsi_shop_outgoing_auth_token', '');

        if (! empty($stored)) {
            return trim(str_ireplace('Bearer ', '', $stored));
        }

        return trim(str_ireplace('Bearer ', '', config('tapsi.auth_token', '')));
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
