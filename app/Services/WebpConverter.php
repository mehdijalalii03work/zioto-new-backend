<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class WebpConverter
{
    private const QUALITY = 82;

    public static function convertToWebp(string $filePath): ?string
    {
        $mimeType = self::getImageMimeType($filePath);

        if (! $mimeType || self::isAnimatedImage($filePath, $mimeType)) {
            return null;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $webpPath = $extension === 'webp'
            ? $filePath
            : dirname($filePath).DIRECTORY_SEPARATOR.pathinfo($filePath, PATHINFO_FILENAME).'.webp';
        $temporaryPath = tempnam(dirname($webpPath), '.webp-');

        if ($temporaryPath === false) {
            return null;
        }

        try {
            if (! self::encode($filePath, $temporaryPath)) {
                return null;
            }

            if ($webpPath === $filePath && filesize($temporaryPath) >= filesize($filePath)) {
                return $filePath;
            }

            if (! @rename($temporaryPath, $webpPath)) {
                return null;
            }

            if ($filePath !== $webpPath) {
                @unlink($filePath);
            }

            return $webpPath;
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    public static function convertUploadedFile(UploadedFile $file): UploadedFile
    {
        $originalPath = $file->getRealPath();

        if (! $originalPath) {
            return $file;
        }

        $mimeType = self::getImageMimeType($originalPath);

        if (! $mimeType || self::isAnimatedImage($originalPath, $mimeType)) {
            return $file;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'webp_');

        if ($temporaryPath === false) {
            return $file;
        }

        if (! self::encode($originalPath, $temporaryPath)) {
            @unlink($temporaryPath);

            return $file;
        }

        if ($mimeType === 'image/webp' && filesize($temporaryPath) >= filesize($originalPath)) {
            @unlink($temporaryPath);

            if (strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)) === 'webp') {
                return $file;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'webp_');

            if ($temporaryPath === false || ! @copy($originalPath, $temporaryPath)) {
                if ($temporaryPath) {
                    @unlink($temporaryPath);
                }

                return $file;
            }
        }

        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.webp';

        return new UploadedFile($temporaryPath, $filename, 'image/webp', null, true);
    }

    private static function getImageMimeType(string $path): ?string
    {
        if (! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $imageInfo = @getimagesize($path);
        $mimeType = $imageInfo['mime'] ?? null;

        return is_string($mimeType) && str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml'
            ? $mimeType
            : null;
    }

    private static function isAnimatedImage(string $path, string $mimeType): bool
    {
        if (! in_array($mimeType, ['image/gif', 'image/png', 'image/webp'], true)) {
            return false;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents)) {
            return false;
        }

        // GD only encodes the first frame. Keep animated GIF, APNG, and WebP files intact.
        return match ($mimeType) {
            'image/gif' => preg_match_all('/\x21\xF9\x04/', $contents) > 1,
            'image/png' => str_contains($contents, 'acTL'),
            'image/webp' => str_contains($contents, 'ANIM'),
        };
    }

    private static function encode(string $sourcePath, string $destinationPath): bool
    {
        $contents = @file_get_contents($sourcePath);
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof \GdImage) {
            return false;
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        try {
            return @imagewebp($image, $destinationPath, self::QUALITY)
                && is_file($destinationPath)
                && filesize($destinationPath) > 0;
        } finally {
            imagedestroy($image);
        }
    }
}
