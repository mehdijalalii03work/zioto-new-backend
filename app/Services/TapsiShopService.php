<?php

namespace App\Services;

use App\Models\Setting;
use Generator;
use Illuminate\Support\Carbon;
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

    /**
     * API 3 — Retrieve a paginated list of orders from Tapsi Shop.
     */
    public function getOrders(int $pageNumber = 0, int $pageSize = 20, array $filters = []): array
    {
        $this->ensureToken();

        $url = config('tapsi.base_url').'/orders';

        $fromDate = $filters['fromDate'] ?? now()->subYear()->toIso8601String();
        $toDate = $filters['toDate'] ?? now()->toIso8601String();

        // Ensure dates are in UTC with Z suffix for Tapsi API compatibility
        $fromDate = $this->normalizeApiDate($fromDate);
        $toDate = $this->normalizeApiDate($toDate);

        $payload = [
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'dateFilterTypeCode' => $filters['dateFilterTypeCode'] ?? 0,
            'orderId' => $filters['orderId'] ?? null,
            'orderNumber' => $filters['orderNumber'] ?? null,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'bundleId' => $filters['bundleId'] ?? null,
            'shippingStatusType' => $filters['shippingStatusType'] ?? [],
            'productId' => $filters['productId'] ?? [],
            'categoryIds' => $filters['categoryIds'] ?? [],
            'orderStatusId' => $filters['orderStatusId'] ?? [],
            'deliveryMethod' => $filters['deliveryMethod'] ?? null,
        ];

        return $this->requestWithRetry('POST', $url, $payload);
    }

    /**
     * API 4 — Retrieve full details for a single order.
     */
    public function getOrderDetails(string $orderId): array
    {
        $this->ensureToken();

        $url = config('tapsi.base_url').'/orders/'.$orderId;

        return $this->requestWithRetry('GET', $url);
    }

    /**
     * Yield every successful (fully-delivered) order from Tapsi Shop page by page.
     *
     * The Tapsi API only allows a 7-day window per request, so we chunk
     * the full date range into 7-day segments automatically.
     *
     * @return Generator<array>
     */
    public function getAllSuccessfulOrders(
        ?string $fromDate = null,
        ?string $toDate = null,
        int $pageSize = 20,
    ): Generator {
        $start = Carbon::parse($fromDate ?? now()->subYear());
        $end = Carbon::parse($toDate ?? now());

        while ($start->lte($end)) {
            $chunkEnd = $start->copy()->addDays(6)->endOfDay();

            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy()->endOfDay();
            }

            Log::info('[TapsiShop] Fetching orders chunk', [
                'from' => $start->toIso8601String(),
                'to' => $chunkEnd->toIso8601String(),
            ]);

            foreach ($this->getOrdersInWindow($start->toIso8601String(), $chunkEnd->toIso8601String(), $pageSize) as $item) {
                yield $item;
            }

            $start = $chunkEnd->addSecond();
        }
    }

    /**
     * Fetch all pages of orders within a single date window (max 7 days).
     *
     * @return Generator<array>
     */
    private function getOrdersInWindow(string $fromDate, string $toDate, int $pageSize): Generator
    {
        $pageNumber = 0;

        do {
            $result = $this->getOrders($pageNumber, $pageSize, [
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'orderStatusId' => ['9'], // Fully Delivered
            ]);

            if (! ($result['success'] ?? false)) {
                Log::error('[TapsiShop] Failed to fetch orders page', [
                    'page' => $pageNumber,
                    'response' => $result,
                ]);

                return;
            }

            $items = $result['data']['items'] ?? [];
            $totalItems = $result['data']['totalItems'] ?? 0;

            Log::info('[TapsiShop] Orders page fetched', [
                'page' => $pageNumber,
                'items_in_page' => count($items),
                'total_items' => $totalItems,
            ]);

            foreach ($items as $item) {
                yield $item;
            }

            $pageNumber++;

            if (($pageNumber * $pageSize) >= $totalItems) {
                break;
            }

            sleep(1);

        } while (true);
    }

    /**
     * Ensure we have a valid token, fetch it if empty.
     */
    private function ensureToken(): void
    {
        if (empty($this->token)) {
            $this->token = $this->getToken();
        }
    }

    /**
     * Normalize a date string to UTC ISO-8601 with Z suffix for the Tapsi API.
     */
    private function normalizeApiDate(string $date): string
    {
        $carbon = Carbon::parse($date)->utc();

        return $carbon->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Make an HTTP request with automatic 401-retry (token refresh).
     */
    private function requestWithRetry(string $method, string $url, ?array $payload = null, int $attempt = 1): array
    {
        $http = Http::withHeaders([
            'Accept' => 'text/plain',
            'Content-Type' => 'application/json',
            'TapsiShop.Hub.Authorization' => $this->token,
        ])->timeout(30);

        $response = match ($method) {
            'GET' => $http->get($url),
            'POST' => $http->post($url, $payload),
            default => $http->send($method, $url, $payload ? ['json' => $payload] : []),
        };

        if ($response->failed()) {
            // 429 Too Many Requests — wait 12 seconds and retry (Tapsi limit: 1 req/10s)
            if ($response->status() === 429 && $attempt < 3) {
                Log::info('[TapsiShop] Got 429 rate limit, waiting 12s and retrying...');

                sleep(12);

                return $this->requestWithRetry($method, $url, $payload, $attempt + 1);
            }

            if ($response->status() === 401 && $attempt < 3) {
                Log::info('[TapsiShop] Got 401, refreshing token and retrying...');

                $newToken = $this->refreshToken($this->token);

                if (! empty($newToken)) {
                    $this->token = $newToken;

                    return $this->requestWithRetry($method, $url, $payload, $attempt + 1);
                }
            }

            Log::error('[TapsiShop] API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            return ['success' => false, 'messages' => [], 'data' => null];
        }

        return $response->json();
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
