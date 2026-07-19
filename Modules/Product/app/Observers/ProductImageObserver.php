<?php

namespace Modules\Product\Observers;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Product\Models\ProductImage;

class ProductImageObserver
{
    private array $sizes = [
        'thumb' => 300,
        'medium' => 600,
        'full' => 1000,
    ];

    public function created(ProductImage $image): void
    {
        $this->generateResponsiveImages($image);
    }

    public function updated(ProductImage $image): void
    {
        if ($image->isDirty('image_path')) {
            $this->deleteResponsiveImages($image->getOriginal('image_path'));
            $this->generateResponsiveImages($image);
        }
    }

    public function deleted(ProductImage $image): void
    {
        $this->deleteResponsiveImages($image->image_path);
    }

    private function generateResponsiveImages(ProductImage $image): void
    {
        $disk = 'public';
        $path = $image->image_path;

        if (! Storage::disk($disk)->exists($path)) {
            return;
        }

        $manager = new ImageManager(new Driver);
        $original = $manager->decodePath(Storage::disk($disk)->path($path));

        $basePath = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        foreach ($this->sizes as $label => $maxWidth) {
            if ($original->width() <= $maxWidth) {
                continue;
            }

            $resized = $original->resizeDown(width: $maxWidth);
            $newPath = $basePath.'/'.$filename.'_'.$label.'.webp';
            $encoded = $resized->encodeUsingMediaType('image/webp', quality: 80);

            Storage::disk($disk)->put($newPath, $encoded->toString());
        }
    }

    private function deleteResponsiveImages(string $path): void
    {
        $disk = 'public';
        $basePath = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        foreach ($this->sizes as $label => $maxWidth) {
            $thumbPath = $basePath.'/'.$filename.'_'.$label.'.webp';
            if (Storage::disk($disk)->exists($thumbPath)) {
                Storage::disk($disk)->delete($thumbPath);
            }
        }
    }
}
