<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imagePath = $this->image_path;

        return [
            'id' => $this->id,
            'path' => asset('storage/'.$imagePath),
            'alt' => $this->alt ?? '',
            'is_primary' => $this->is_primary,
            'responsive' => self::getResponsiveImages($imagePath),
            'srcset' => self::getSrcset(self::getResponsiveImages($imagePath)),
        ];
    }

    public static function getResponsiveImages(string $imagePath): array
    {
        $disk = 'public';
        $basePath = pathinfo($imagePath, PATHINFO_DIRNAME);
        $filename = pathinfo($imagePath, PATHINFO_FILENAME);

        $sizes = [
            'thumb' => 300,
            'medium' => 600,
            'full' => 1000,
        ];

        $variants = ['original' => asset('storage/'.$imagePath)];

        foreach ($sizes as $label => $maxWidth) {
            $variantPath = $basePath.'/'.$filename.'_'.$label.'.webp';
            if (Storage::disk($disk)->exists($variantPath)) {
                $variants[$label] = asset('storage/'.$variantPath);
            }
        }

        return $variants;
    }

    public static function getSrcset(array $responsiveImages): string
    {
        $parts = [];
        $widthMap = ['thumb' => '300w', 'medium' => '600w', 'full' => '1000w', 'original' => '1200w'];
        foreach ($responsiveImages as $label => $url) {
            if (isset($widthMap[$label])) {
                $parts[] = $url.' '.$widthMap[$label];
            }
        }

        return implode(', ', $parts);
    }
}
