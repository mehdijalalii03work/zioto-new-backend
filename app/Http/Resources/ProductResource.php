<?php

namespace App\Http\Resources;

use App\Models\Setting;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\SalesRestrictionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        $livePrice = app(DynamicPriceService::class)->priceFor($this->resource, $request->user()?->id);
        $price = (int) ($livePrice ?? $this->price);

        $taxKey = str_starts_with($this->price_board_item ?? '', 'Gold') || ($this->metal_type?->value ?? null) === 'gold'
            ? 'tax_gold'
            : 'tax_silver';
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        $salesRestriction = app(SalesRestrictionService::class);
        $blockedReason = $salesRestriction->blockedReason($this->resource);

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
            'weight' => $this->weight ? $this->weight.' گرم' : '',
            'price' => $price,
            'price_before_tax' => $priceBeforeTax,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'old' => null,
            'badge' => null,
            'stock' => $this->stock_quantity > 0,
            'contact_only' => (bool) $this->contact_only,
            'is_nopay' => (bool) $this->is_nopay,
            'purchasable' => $blockedReason === null,
            'unavailable_reason' => $blockedReason,
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
