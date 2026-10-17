<?php

namespace App\Http\Resources;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = (int) $this->price;
        $packSize = $this->pack_size;
        $unitPrice = $packSize > 1 ? (int) round($price / $packSize) : $price;
        $primaryImage = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        $taxKey = str_starts_with($this->price_board_item ?? '', 'Gold') ? 'tax_gold' : 'tax_silver';
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        $weight = $this->weight ? $this->weight.' گرم' : '';
        if ($packSize > 1 && $weight !== '') {
            $weight .= ' × '.$packSize.' عدد';
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'sub' => $this->category?->name ?? '',
            'cat' => $this->category?->name ?? '',
            'cat_slug' => $this->category?->slug ?? '',
            'brand' => $this->brand?->name ?? '',
            'brand_slug' => $this->brand?->slug ?? '',
            'metal_type' => $this->metal_type?->value ?? null,
            'metal_type_label' => $this->metal_type?->label() ?? null,
            'form' => $this->form?->value ?? null,
            'form_label' => $this->form?->label() ?? null,
            'ayar' => $this->ayar?->value ?? null,
            'ayar_label' => $this->ayar?->label() ?? null,
            'weight' => $weight,
            'pack_size' => $packSize,
            'unit_price' => $unitPrice,
            'price' => $price,
            'price_before_tax' => $priceBeforeTax,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'old' => null,
            'badge' => $packSize > 1 ? 'پک '.$packSize.' عددی' : null,
            'stock' => $this->sellable_stock > 0,
            'contact_only' => (bool) $this->contact_only,
            'is_nopay' => (bool) $this->is_nopay,
            'desc' => strip_tags($this->description ?? ''),
            'full_desc' => $this->description ?? '',
            'image' => $primaryImage ? asset('storage/'.$primaryImage->image_path) : null,
            'image_responsive' => $primaryImage ? ProductImageResource::getResponsiveImages($primaryImage->image_path) : null,
            'image_srcset' => $primaryImage ? ProductImageResource::getSrcset(ProductImageResource::getResponsiveImages($primaryImage->image_path)) : null,
            'images' => ProductImageResource::collection($this->images->sortBy('sort_order')),
        ];
    }

    public static function withoutImages(self $resource): array
    {
        $data = $resource->toArray(request());
        unset($data['images'], $data['full_desc'], $data['sku']);

        return $data;
    }
}
