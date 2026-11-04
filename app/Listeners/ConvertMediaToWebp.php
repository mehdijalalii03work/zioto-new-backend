<?php

namespace App\Listeners;

use App\Services\WebpConverter;
use Spatie\MediaLibrary\MediaCollections\Events\MediaAdded;

class ConvertMediaToWebp
{
    public function handle(MediaAdded $event): void
    {
        $media = $event->media;

        $fullPath = $media->getPath();

        if (! file_exists($fullPath)) {
            return;
        }

        $webpPath = WebpConverter::convertToWebp($fullPath);

        if (! $webpPath) {
            return;
        }

        $newFilename = basename($webpPath);

        $media->update([
            'file_name' => $newFilename,
            'mime_type' => 'image/webp',
        ]);
    }
}
