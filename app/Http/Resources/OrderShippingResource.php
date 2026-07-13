<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderShippingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipping_method_name' => $this->shipping_method_name,
            'shipping_cost' => $this->shipping_cost,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'estimated_delivery_min' => $this->estimated_delivery_min,
            'estimated_delivery_max' => $this->estimated_delivery_max,
            'delivered_at' => $this->delivered_at,
        ];
    }
}
