<?php

namespace App\Services;

use App\Models\PriceHistory;
use Illuminate\Support\Facades\Log;
use Throwable;

class PriceHistoryService
{
    public function logBoardPrices(array $prices): void
    {
        // New Zioto shape: payload with 'prices' key. Legacy Tokeniko: 'products'.
        $boardItems = $prices['prices'] ?? $prices['products'] ?? [];

        if (empty($boardItems)) {
            return;
        }

        $this->write([
            'type' => 'board',
            'source' => 'zioto_board',
            'items_count' => count($boardItems),
            'data' => $prices,
        ]);
    }

    public function logDirectPrices(array $prices): void
    {
        if (empty($prices)) {
            return;
        }

        $this->write([
            'type' => 'product',
            'source' => 'tokeniko_shop',
            'items_count' => count($prices),
            'data' => $prices,
        ]);
    }

    /**
     * Price history lives in MongoDB and is only observational: a write failure
     * must never abort the sync that produced the prices.
     */
    private function write(array $record): void
    {
        // Skip if MongoDB extension is not available
        if (! extension_loaded('mongodb')) {
            return;
        }

        try {
            PriceHistory::create($record);
        } catch (Throwable $e) {
            Log::warning('[PriceHistory] Unable to persist: '.$e->getMessage());
        }
    }
}
