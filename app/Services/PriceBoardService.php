<?php

namespace App\Services;

use App\Services\Pricing\ApiClientService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Public facade over the Zioto price board (PersianAPI + Tala.ir).
 *
 * Replaces the previous Tokeniko board while keeping the method surface
 * used by controllers, sync command, and product pricing.
 */
class PriceBoardService
{
    public function __construct(
        private readonly ApiClientService $apiClient,
    ) {}

    /**
     * Fetch fresh prices, cache them, and return the full payload.
     *
     * @return array{prices: array, debug: array, source_status: array, raw: array, used_fallback: bool}
     */
    public function fetchAndStore(): array
    {
        return $this->apiClient->getAllPrices(forceRefresh: true);
    }

    /**
     * Get cached payload (or empty structure).
     *
     * @return array{prices: array, debug: array, source_status: array, raw: array, used_fallback: bool}
     */
    public function getPrices(): array
    {
        $payload = $this->apiClient->readCache();

        if ($payload === null) {
            $payload = $this->apiClient->getAllPrices();
        }

        return $payload;
    }

    /**
     * Just the board map: [Gold995_Sell => [...], ...]
     *
     * @return array<string, array>
     */
    public function getBoardPrices(): array
    {
        return $this->getPrices()['prices'] ?? [];
    }

    public function getLastSyncAt(): ?Carbon
    {
        $value = Cache::get('priceboard:last_sync_at');

        if (! $value || $value instanceof \__PHP_Incomplete_Class) {
            return null;
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return null;
    }

    public function getDebugRows(): array
    {
        return $this->apiClient->getDebugRows();
    }

    public function getSourceStatus(): array
    {
        return $this->apiClient->getSourceStatus();
    }

    public function getBasePrices(): array
    {
        return $this->apiClient->getBasePrices();
    }

    public function refresh(): array
    {
        return $this->apiClient->refresh();
    }

    public function clearCache(): void
    {
        foreach (['zioto:payload', 'zioto:prices', 'zioto:debug', 'zioto:source_status', 'zioto:raw', 'priceboard:prices', 'priceboard:last_sync_at'] as $key) {
            Cache::forget($key);
        }
    }
}
