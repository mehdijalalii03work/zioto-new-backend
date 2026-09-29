<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_price' => $this->product_price,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal,
            'price_board_item' => $this->price_board_item,
            'board_price' => $this->board_price,
            'weight' => $this->weight,
            'labor_coefficient' => $this->labor_coefficient,
            'labor_cost' => $this->labor_cost,
            'taxes' => $this->taxes,
            'final_price' => $this->final_price,
        ];
    }
}
