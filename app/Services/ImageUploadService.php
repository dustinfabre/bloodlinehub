<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageUploadService
{
    /**
     * Upload and optimize an image to R2.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param int $maxWidth
     * @param int $quality
     * @return string The public URL of the uploaded image
     */
    public function upload(UploadedFile $file, string $folder = 'images', int $maxWidth = 1200, int $quality = 85): string
    {
        // Generate unique filename
        $filename = Str::uuid() . '.webp';
        $path = "{$folder}/{$filename}";

        // Load and optimize image
        $image = Image::read($file);

        // Resize if larger than max width (maintaining aspect ratio)
        if ($image->width() > $maxWidth) {
            $image->scale(width: $maxWidth);
        }

        // Convert to WebP and encode with quality
        $encodedImage = $image->toWebp($quality);

        // Upload to R2
        Storage::disk('r2')->put($path, $encodedImage);

        // Return public URL
        return Storage::disk('r2')->url($path);
    }

    /**
     * Upload multiple images.
     *
     * @param array<UploadedFile> $files
     * @param string $folder
     * @param int $maxWidth
     * @param int $quality
     * @return array<string> Array of public URLs
     */
    public function uploadMultiple(array $files, string $folder = 'images', int $maxWidth = 1200, int $quality = 85): array
    {
        return array_map(
            fn(UploadedFile $file) => $this->upload($file, $folder, $maxWidth, $quality),
            $files
        );
    }

    /**
     * Delete an image from R2 by its URL.
     *
     * @param string|null $url
     * @return bool
     */
    public function delete(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        // Extract path from URL
        $path = $this->getPathFromUrl($url);

        if ($path && Storage::disk('r2')->exists($path)) {
            return Storage::disk('r2')->delete($path);
        }

        return false;
    }

    /**
     * Delete multiple images from R2.
     *
     * @param array<string> $urls
     * @return void
     */
    public function deleteMultiple(array $urls): void
    {
        foreach ($urls as $url) {
            $this->delete($url);
        }
    }

    /**
     * Extract the storage path from a public URL.
     *
     * @param string $url
     * @return string|null
     */
    private function getPathFromUrl(string $url): ?string
    {
        $publicUrl = config('filesystems.disks.r2.url');
        
        if (str_starts_with($url, $publicUrl)) {
            return str_replace($publicUrl . '/', '', $url);
        }

        return null;
    }
}
