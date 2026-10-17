<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = (int) ($this->product->price ?? 0);
        $packSize = $this->product ? $this->product->pack_size : 1;
        $unitPrice = $packSize > 1 ? (int) round($price / $packSize) : $price;

        return [
            'id' => $this->product_id,
            'qty' => $this->quantity,
            'name' => $this->product->name ?? '',
            'price' => $price,
            'unit_price' => $unitPrice,
            'pack_size' => $packSize,
            'weight' => $this->product->weight ?? '',
            'stock' => $this->product ? $this->product->sellable_stock > 0 : false,
        ];
    }
}
