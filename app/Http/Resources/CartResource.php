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
        $packSize = $product ? $product->pack_size : 1;
        $unitPrice = $packSize > 1 ? (int) round($price / $packSize) : $price;

        return [
            'id' => $this->product_id,
            'qty' => $this->quantity,
            'name' => $product->name ?? '',
            'price' => $price,
            'unit_price' => $unitPrice,
            'pack_size' => $packSize,
            'weight' => $product->weight ?? '',
            'stock' => $product ? $product->sellable_stock > 0 : false,
        ];
    }
}
