<?php

namespace App\Http\Resources;

use App\Services\Pricing\DynamicPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $userId = $request->user()?->id;

        $livePrice = null;
        if ($product) {
            $livePrice = app(DynamicPriceService::class)->priceFor($product, $userId);
        }

        $price = (int) ($livePrice ?? $product?->price ?? 0);

        return [
            'id' => $this->product_id,
            'qty' => $this->quantity,
            'name' => $product->name ?? '',
            'price' => $price,
            'weight' => $product->weight ?? '',
            'stock' => ($product->stock_quantity ?? 0) > 0,
        ];
    }
}
