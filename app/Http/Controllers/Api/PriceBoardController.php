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
        $prices = $this->priceBoard->getPrices();

        if (empty($prices)) {
            $prices = $this->priceBoard->fetchAndStore();
        }

        return response()->json([
            'data' => $prices,
        ]);
    }
}
