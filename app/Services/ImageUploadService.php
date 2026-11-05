<?php

namespace App\Services;

use App\Models\DriveFile;
use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Services\GoogleDrive\GoogleDrive;
use App\Services\GoogleDrive\GoogleDriveException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Saves images to the uploader's own Google Drive. The app keeps no image files, only a DriveFile row per image,
 * and serves them back through MediaController.
 */
class ImageUploadService
{
    public function __construct(private GoogleDrive $drive) {}

    /**
     * Optimize an image and save it to the user's Google Drive.
     *
     * @param  string  $name  File name in Drive, without extension (e.g. "PH-2025-1234 photo")
     * @return string The app URL that serves the image (/media/{id}.webp)
     *
     * @throws GoogleDriveException When no Drive is connected or Drive refuses the file
     */
    public function upload(UploadedFile $file, User $user, string $name, int $maxWidth = 1200, int $quality = 85): string
    {
        $connection = $this->connection($user);

        $image = Image::read($file);

        // Resize if larger than max width (maintaining aspect ratio)
        if ($image->width() > $maxWidth) {
            $image->scale(width: $maxWidth);
        }

        $contents = (string) $image->toWebp($quality);

        $uploaded = $this->drive->upload($connection, $this->fileName($name), $contents, 'image/webp');

        return DriveFile::create([
            'user_id' => $user->id,
            'drive_file_id' => $uploaded['id'],
            'mime_type' => 'image/webp',
            'size' => $uploaded['size'],
        ])->url();
    }

    /**
     * Upload several images, numbering their Drive names when there is more than one.
     *
     * @param  array<UploadedFile>  $files
     * @return array<string> App URLs, in the same order
     */
    public function uploadMultiple(array $files, User $user, string $name, int $maxWidth = 1200, int $quality = 85): array
    {
        $files = array_values($files);
        $numbered = count($files) > 1;

        return array_map(
            fn (UploadedFile $file, int $i) => $this->upload($file, $user, $numbered ? "{$name} ".($i + 1) : $name, $maxWidth, $quality),
            $files,
            array_keys($files),
        );
    }

    /**
     * Stop serving one of the user's images and move it to their Drive trash. URLs that are not the user's own
     * Drive images (another user's, or an old external URL) are left alone.
     */
    public function delete(?string $url, User $user): bool
    {
        $id = DriveFile::idFromUrl($url);
        $driveFile = $id ? DriveFile::where('user_id', $user->id)->find($id) : null;

        if (! $driveFile) {
            return false;
        }

        $connection = GoogleDriveConnection::forUser($user);

        if ($connection) {
            try {
                $this->drive->trash($connection, $driveFile->drive_file_id);
            } catch (GoogleDriveException $e) {
                // The image still leaves the app; the copy simply stays in the user's Drive folder
                Log::info('Could not trash a Drive file', ['drive_file' => $driveFile->id, 'message' => $e->getMessage()]);
            }
        }

        $driveFile->delete();

        return true;
    }

    /**
     * @param  array<string>  $urls
     */
    public function deleteMultiple(array $urls, User $user): void
    {
        foreach ($urls as $url) {
            $this->delete($url, $user);
        }
    }

    private function connection(User $user): GoogleDriveConnection
    {
        return GoogleDriveConnection::forUser($user)
            ?? throw new GoogleDriveException('Connect your Google Drive in Settings → Storage to add photos.');
    }

    private function fileName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', ' ', $name) ?? '');

        return Str::limit($name !== '' ? $name : 'image', 120, '').'.webp';
    }
}
