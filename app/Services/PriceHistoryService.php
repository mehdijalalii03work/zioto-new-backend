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

        foreach ($products as $item) {
            $name = $item['name'] ?? null;

            if (! $name) {
                continue;
            }

            PriceHistory::create([
                'type' => 'board',
                'item_name' => $name,
                'sell_price' => (int) ($item['sellPrice'] ?? 0),
                'change_percent' => isset($item['changePercent']) ? (float) $item['changePercent'] : null,
                'source' => 'tokeniko_board',
                'metadata' => [
                    'change' => $item['change'] ?? null,
                ],
            ]);
        }
    }

    public function logDirectPrices(array $prices): void
    {
        if (empty($prices)) {
            return;
        }

        foreach ($prices as $name => $sellPrice) {
            PriceHistory::create([
                'type' => 'product',
                'item_name' => $name,
                'sell_price' => (int) $sellPrice,
                'change_percent' => null,
                'source' => 'tokeniko_shop',
                'metadata' => null,
            ]);
        }
    }
}
