<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->product_id,
            'qty' => $this->quantity,
            'name' => $this->product->name ?? '',
            'price' => (int) ($this->product->price ?? 0),
            'weight' => $this->product->weight ?? '',
            'stock' => ($this->product->stock_quantity ?? 0) > 0,
        ];
    }
}
