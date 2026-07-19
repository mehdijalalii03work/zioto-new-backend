<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class WebpConverter
{
    private static ?ImageManagerStatic $manager = null;

    public static function convertToWebp(string $filePath): ?string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return null;
        }

        if (! file_exists($filePath)) {
            return null;
        }

        $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $filePath);

        $image = self::loadImage($filePath, $extension);

        if (! $image) {
            return null;
        }

        if (imageistruecolor($image) === false) {
            $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
            imagefilledrectangle($trueColor, 0, 0, imagesx($image) - 1, imagesy($image) - 1, $transparent);
            imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $trueColor;
        }

        $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $filePath);
        $result = imagewebp($image, $webpPath, 82);
        imagedestroy($image);

        if (! $result || ! file_exists($webpPath)) {
            return null;
        }

        if ($filePath !== $webpPath) {
            @unlink($filePath);
        }

        return $webpPath;
    }

    public static function convertUploadedFile(UploadedFile $file): UploadedFile
    {
        $originalPath = $file->getRealPath();
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return $file;
        }

        $image = self::loadImage($originalPath, $extension);

        if (! $image) {
            return $file;
        }

        $webpPath = tempnam(sys_get_temp_dir(), 'webp_').'.webp';
        $result = imagewebp($image, $webpPath, 82);
        imagedestroy($image);

        if (! $result || ! file_exists($webpPath)) {
            return $file;
        }

        $newName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $file->getClientOriginalName());

        return new UploadedFile(
            $webpPath,
            $newName,
            'image/webp',
            null,
            true
        );
    }

    private static function loadImage(string $path, string $extension): ?\GdImage
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            default => null,
        };
    }
}
