<?php

namespace App\Services;

use App\Models\PriceHistory;

class PriceHistoryService
{
    public function logBoardPrices(array $prices): void
    {
        $products = $prices['products'] ?? [];

        if (empty($products)) {
            return;
        }

        PriceHistory::create([
            'type' => 'board',
            'source' => 'tokeniko_board',
            'items_count' => count($products),
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
