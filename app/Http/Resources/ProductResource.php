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
        return $this->productArray($request, includeDetailFields: true);
    }

    public function toListingArray(Request $request): array
    {
        return $this->productArray($request, includeDetailFields: false);
    }

    private function productArray(Request $request, bool $includeDetailFields): array
    {
        $packSize = $this->pack_size;
        $primaryImage = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        $livePrice = app(DynamicPriceService::class)->priceFor($this->resource, $request->user()?->id);
        $price = (int) ($livePrice ?? $this->price);
        $unitPrice = $packSize > 1 ? (int) round($price / $packSize) : $price;

        $taxKey = str_starts_with($this->price_board_item ?? '', 'Gold') || ($this->metal_type?->value ?? null) === 'gold'
            ? 'tax_gold'
            : 'tax_silver';
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        $salesRestriction = app(SalesRestrictionService::class);
        $blockedReason = $salesRestriction->blockedReason($this->resource);
        $weight = $this->weight ? $this->weight.' گرم' : '';
        if ($packSize > 1 && $weight !== '') {
            $weight .= ' × '.$packSize.' عدد';
        }

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
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
            'purchasable' => $blockedReason === null,
            'unavailable_reason' => $blockedReason,
            'image' => $primaryImage ? asset('storage/'.$primaryImage->image_path) : null,
        ];

        if ($includeDetailFields) {
            $responsiveImages = $primaryImage ? ProductImageResource::getResponsiveImages($primaryImage->image_path) : null;
            $data['sku'] = $this->sku;
            $data['desc'] = strip_tags($this->description ?? '');
            $data['full_desc'] = $this->description ?? '';
            $data['image_responsive'] = $responsiveImages;
            $data['image_srcset'] = $responsiveImages ? ProductImageResource::getSrcset($responsiveImages) : null;
            $data['images'] = ProductImageResource::collection($this->images->sortBy('sort_order'));
        }

        return $data;
    }

    public static function withoutImages(self $resource): array
    {
        $data = $resource->toArray(request());
        unset($data['images'], $data['full_desc'], $data['sku']);

        return $data;
    }
}
