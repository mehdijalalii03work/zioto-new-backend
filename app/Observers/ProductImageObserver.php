<?php

namespace App\Observers;

use App\Services\WebpConverter;
use Modules\Product\Models\ProductImage;

class ProductImageObserver
{
    public function created(ProductImage $productImage): void
    {
        $this->convertImage($productImage);
    }

    public function updated(ProductImage $productImage): void
    {
        if ($productImage->isDirty('image_path')) {
            $oldPath = $productImage->getOriginal('image_path');
            if ($oldPath && file_exists(storage_path('app/public/'.$oldPath))) {
                $oldExt = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));
                if (in_array($oldExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $oldWebp = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $oldPath);
                    $oldWebpFile = storage_path('app/public/'.$oldWebp);
                    if (file_exists($oldWebpFile)) {
                        @unlink($oldWebpFile);
                    }
                }
            }
            $this->convertImage($productImage);
        }
    }

    private function convertImage(ProductImage $productImage): void
    {
        $imagePath = $productImage->image_path;

        if (! $imagePath) {
            return;
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return;
        }

        $fullPath = storage_path('app/public/'.$imagePath);

        if (! file_exists($fullPath)) {
            return;
        }

        $webpPath = WebpConverter::convertToWebp($fullPath);

        if (! $webpPath) {
            return;
        }

        $newRelativePath = str_replace(storage_path('app/public/').'/', '', $webpPath);

        $productImage->updateQuietly(['image_path' => $newRelativePath]);
    }
}
