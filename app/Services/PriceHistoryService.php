<?php

namespace App\Services;

use App\Models\PriceHistory;

class PriceHistoryService
{
    public function logBoardPrices(array $prices): void
    {
        // New Zioto shape: payload with 'prices' key. Legacy Tokeniko: 'products'.
        $boardItems = $prices['prices'] ?? $prices['products'] ?? [];

        if (empty($boardItems)) {
            return;
        }

        PriceHistory::create([
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

        PriceHistory::create([
            'type' => 'product',
            'source' => 'tokeniko_shop',
            'items_count' => count($prices),
            'data' => $prices,
        ]);
    }
}
