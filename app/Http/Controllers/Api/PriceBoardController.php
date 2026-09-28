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
                'products' => $payload['prices'] ?? [],
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
                'products' => $payload['prices'] ?? [],
                'source_status' => $payload['source_status'] ?? [],
                'updated_at' => $this->priceBoard->getLastSyncAt()?->toIso8601String(),
            ],
        ]);
    }
}
