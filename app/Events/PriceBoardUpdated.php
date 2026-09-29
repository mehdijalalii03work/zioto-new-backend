<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PriceBoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly array $prices,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('price-board'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'prices.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'prices' => $this->transformPrices($this->prices),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Transform backend price format to frontend expected format.
     *
     * Backend: ['Gold750_Sell' => ['value' => 24744909, 'price_type' => 'sell', ...], 'Gold750_Buy' => [...]]
     * Frontend: [{'name' => 'Gold750', 'sellPrice' => 24744909, 'buyPrice' => 24401200, 'changeAmount' => 0, 'changePercent' => 0}, ...]
     */
    private function transformPrices(array $prices): array
    {
        $grouped = [];

        foreach ($prices as $key => $priceData) {
            $baseMetal = $priceData['base_metal'] ?? '';
            $priceType = $priceData['price_type'] ?? '';
            $value = (float) ($priceData['value'] ?? 0);
            $trend = $priceData['trend'] ?? 'stable';

            if (! $baseMetal || ! $priceType || $value <= 0) {
                continue;
            }

            if (! isset($grouped[$baseMetal])) {
                $grouped[$baseMetal] = [
                    'name' => $baseMetal,
                    'sellPrice' => 0,
                    'buyPrice' => 0,
                    'changeAmount' => 0,
                    'changePercent' => 0,
                    'trend' => 'stable',
                ];
            }

            if ($priceType === 'sell') {
                $grouped[$baseMetal]['sellPrice'] = $value;
            } elseif ($priceType === 'buy') {
                $grouped[$baseMetal]['buyPrice'] = $value;
            }

            $grouped[$baseMetal]['trend'] = $trend;
        }

        foreach ($grouped as &$item) {
            $sell = $item['sellPrice'];
            $buy = $item['buyPrice'];

            if ($sell > 0 && $buy > 0) {
                $item['changeAmount'] = round($sell - $buy);
                $item['changePercent'] = $buy > 0 ? round((($sell - $buy) / $buy) * 100, 2) : 0;
            }
        }

        return array_values($grouped);
    }
}
