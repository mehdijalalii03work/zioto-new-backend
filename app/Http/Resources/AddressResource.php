<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'province' => $this->province?->name,
            'city' => $this->city?->name,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'address_line' => $this->address_line,
            'plate' => $this->plate,
            'unit' => $this->unit,
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->is_default,
            'is_billing' => $this->is_billing,
            'full_address' => $this->whenLoaded('province', fn () => $this->full_address),
        ];
    }
}
