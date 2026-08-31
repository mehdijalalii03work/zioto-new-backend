<?php

namespace App\Http\Resources;

use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RigeProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->sku,
            'name' => $this->name,
            'description' => strip_tags($this->description ?? ''),
            'category_id' => $this->category_id,
            'weight' => $this->weight ? (float) $this->weight : null,
            'wage' => 0,
            'discount' => 0,
            'in_stock' => ($this->stock_quantity ?? 0) > 0,
            'used' => false,
            'production_code' => $this->sku,
            'variants' => [
                'material' => $this->resolveMaterialId(),
                'caret' => $this->resolveCaretId(),
                'gender' => null,
                'color' => null,
                'brand' => $this->brand?->name ?? null,
                'model' => null,
            ],
        ];
    }

    private function resolveMaterialId(): ?int
    {
        return match ($this->metal_type) {
            MetalType::Gold => 1,
            MetalType::Silver => 2,
            default => null,
        };
    }

    private function resolveCaretId(): ?int
    {
        return match ($this->ayar) {
            Ayar::P995 => 1,
            Ayar::P999 => 2,
            Ayar::P9999 => 3,
            default => null,
        };
    }
}
