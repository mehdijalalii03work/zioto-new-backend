<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConvertImagesToWebp extends Command
{
    protected $signature = 'images:convert-to-webp';

    protected $description = 'Convert all non-webp images to webp and update database records';

    private int $converted = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $extensions = ['jpg', 'jpeg', 'png', 'gif'];

        $this->info('Scanning storage/app/public for non-webp images...');

        $allFiles = $this->getAllFiles($disk, '');
        $imageFiles = array_filter($allFiles, function ($file) use ($extensions) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return in_array($ext, $extensions);
        });

        $this->info('Found '.count($imageFiles).' non-webp images to convert.');
        $this->newLine();

        foreach ($imageFiles as $imagePath) {
            $this->convertImage($disk, $imagePath);
        }

        $this->newLine();
        $this->info("Done! Converted: {$this->converted}, Skipped: {$this->skipped}, Failed: {$this->failed}");

        return Command::SUCCESS;
    }

    private function getAllFiles($disk, string $directory): array
    {
        $files = [];
        $contents = $disk->files($directory);

        foreach ($contents as $file) {
            $basename = basename($file);
            if ($basename === '.gitignore') {
                continue;
            }
            $files[] = $file;
        }

        $directories = $disk->directories($directory);
        foreach ($directories as $dir) {
            $files = array_merge($files, $this->getAllFiles($disk, $dir));
        }

        return $files;
    }

    private function convertImage($disk, string $imagePath): void
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $imagePath);

        if ($disk->exists($webpPath)) {
            $this->skipped++;

            return;
        }

        $fullPath = storage_path('app/public/'.$imagePath);

        if (! file_exists($fullPath)) {
            $this->warn("  File not found: {$imagePath}");
            $this->failed++;

            return;
        }

        $image = $this->loadImage($fullPath, $extension);

        if (! $image) {
            $this->warn("  Failed to load: {$imagePath}");
            $this->failed++;

            return;
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

        $webpFullPath = storage_path('app/public/'.$webpPath);
        $result = imagewebp($image, $webpFullPath, 82);
        imagedestroy($image);

        if (! $result || ! $disk->exists($webpPath)) {
            $this->warn("  Failed to save webp: {$imagePath}");
            $this->failed++;

            return;
        }

        $this->updateDatabase($imagePath, $webpPath);
        $disk->delete($imagePath);

        $this->converted++;
        $this->line("  <info>OK</info> {$imagePath} -> {$webpPath}");
    }

    private function loadImage(string $path, string $extension): ?\GdImage
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'gif' => imagecreatefromgif($path),
            default => null,
        };
    }

    private function updateDatabase(string $oldPath, string $newPath): void
    {
        $oldFilename = basename($oldPath);
        $newFilename = basename($newPath);

        $affected = DB::table('product_images')
            ->where('image_path', 'LIKE', '%'.$oldFilename)
            ->update(['image_path' => $newPath]);

        if ($affected > 0) {
            $this->line("    DB product_images: updated {$affected} row(s)");
        }

        $affected = DB::table('blog_posts')
            ->where('image', 'LIKE', '%'.$oldFilename)
            ->update(['image' => $newPath]);

        if ($affected > 0) {
            $this->line("    DB blog_posts: updated {$affected} row(s)");
        }

        $affected = DB::table('brands')
            ->where('logo', 'LIKE', '%'.$oldFilename)
            ->update(['logo' => $newPath]);

        if ($affected > 0) {
            $this->line("    DB brands: updated {$affected} row(s)");
        }

        DB::table('media')
            ->where('file_name', $oldFilename)
            ->update([
                'file_name' => $newFilename,
                'mime_type' => 'image/webp',
            ]);

        DB::table('media')
            ->where('custom_properties', 'LIKE', '%'.$oldFilename.'%')
            ->where('custom_properties', 'LIKE', '%"generated_conversions"%')
            ->orderBy('id')
            ->each(function ($media) use ($oldFilename, $newFilename) {
                $props = json_decode($media->custom_properties, true);
                if ($props && isset($props['generated_conversions'])) {
                    foreach ($props['generated_conversions'] as $conversion => $path) {
                        if (is_string($path) && str_contains($path, $oldFilename)) {
                            $props['generated_conversions'][$conversion] = str_replace($oldFilename, $newFilename, $path);
                        }
                    }
                }
                DB::table('media')->where('id', $media->id)->update(['custom_properties' => json_encode($props)]);
            });
    }
}
