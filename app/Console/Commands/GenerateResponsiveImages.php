<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Product\Models\ProductImage;

class GenerateResponsiveImages extends Command
{
    protected $signature = 'images:responsive';

    protected $description = 'Generate responsive thumbnails for existing product images';

    public function handle(): int
    {
        $sizes = [
            'thumb' => 300,
            'medium' => 600,
            'full' => 1000,
        ];

        $manager = new ImageManager(new Driver);
        $images = ProductImage::all();
        $count = 0;
        $skipped = 0;

        $this->info('Processing '.$images->count().' product images...');

        foreach ($images as $image) {
            $path = $image->image_path;
            $disk = 'public';

            if (! Storage::disk($disk)->exists($path)) {
                $this->warn('Missing: '.$path);
                $skipped++;

                continue;
            }

            $basePath = pathinfo($path, PATHINFO_DIRNAME);
            $filename = pathinfo($path, PATHINFO_FILENAME);

            $original = $manager->decodePath(Storage::disk($disk)->path($path));

            foreach ($sizes as $label => $maxWidth) {
                $newPath = $basePath.'/'.$filename.'_'.$label.'.webp';

                if (Storage::disk($disk)->exists($newPath)) {
                    continue;
                }

                if ($original->width() <= $maxWidth) {
                    continue;
                }

                $resized = $original->resizeDown(width: $maxWidth);
                $encoded = $resized->encodeUsingMediaType('image/webp', quality: 80);
                Storage::disk($disk)->put($newPath, $encoded->toString());
                $count++;
                $this->line('  Created: '.$newPath);
            }
        }

        $this->info('Done! Created '.$count.' responsive images, skipped '.$skipped.' missing.');

        return 0;
    }
}
