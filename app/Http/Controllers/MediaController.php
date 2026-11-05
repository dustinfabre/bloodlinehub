<?php

namespace App\Http\Controllers;

use App\Models\DriveFile;
use App\Models\GoogleDriveConnection;
use App\Services\GoogleDrive\GoogleDrive;
use App\Services\GoogleDrive\GoogleDriveException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves an image from its owner's Google Drive. Anyone with the URL may view it, as with the public bucket URLs used
 * before: the random id is the secret. The route runs without sessions or cookies (bootstrap/app.php), and a stored
 * image never changes (replacing a photo makes a new id), so browsers and a CDN can keep each response for a year
 * and Drive is only asked once per image per cache.
 */
class MediaController extends Controller
{
    public function __invoke(Request $request, string $file, GoogleDrive $drive): Response
    {
        $etag = '"'.$file.'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $this->cacheHeaders($etag));
        }

        $driveFile = DriveFile::find($file);
        $connection = $driveFile ? GoogleDriveConnection::where('user_id', $driveFile->user_id)->first() : null;

        if (! $driveFile || ! $connection) {
            return $this->unavailable(404);
        }

        try {
            $contents = $drive->download($connection, $driveFile->drive_file_id);
        } catch (GoogleDriveException) {
            return $this->unavailable(503);
        }

        if ($contents === null) {
            return $this->unavailable(404);
        }

        return response($contents, 200, [
            'Content-Type' => $driveFile->mime_type,
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            ...$this->cacheHeaders($etag),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function cacheHeaders(string $etag): array
    {
        return [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ];
    }

    /**
     * Never cached: the owner may reconnect their Drive, and Drive may only be down for a moment.
     */
    private function unavailable(int $status): Response
    {
        return response('', $status, ['Cache-Control' => 'no-store']);
    }
}
