<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PriceBoardService;
use Illuminate\Http\JsonResponse;

class PriceBoardController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoard
    ) {}

    public function index(): JsonResponse
    {
        $payload = $this->priceBoard->getPrices();

        return response()->json([
            'data' => [
                'products' => $this->transformPrices($payload['prices'] ?? []),
                'source_status' => $payload['source_status'] ?? [],
                'updated_at' => $this->priceBoard->getLastSyncAt()?->toIso8601String(),
            ],
        ]);
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->priceBoard->refresh();

        return response()->json([
            'message' => 'تخته قیمت بروزرسانی شد',
            'data' => [
                'products' => $this->transformPrices($payload['prices'] ?? []),
                'source_status' => $payload['source_status'] ?? [],
                'updated_at' => $this->priceBoard->getLastSyncAt()?->toIso8601String(),
            ],
        ]);
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
