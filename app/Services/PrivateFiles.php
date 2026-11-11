<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Private uploads on R2: payment receipts, bank QR codes and feedback screenshots. Nothing here has a public URL;
 * controllers check who may see a file and stream it with stream().
 *
 * Images are re-encoded to WebP, which strips their metadata (e.g. a phone's GPS location) and guarantees the file
 * is the image it claims to be. PDFs are kept as uploaded and served sandboxed.
 */
class PrivateFiles
{
    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Validation rule for an uploaded receipt: a photo or screenshot, or a PDF from online banking.
     *
     * @return array<int, string>
     */
    public static function receiptRules(): array
    {
        return ['file', 'mimes:'.implode(',', [...self::IMAGE_MIMES, 'pdf']), 'max:10240'];
    }

    /**
     * @return array<int, string>
     */
    public static function imageRules(): array
    {
        return ['file', 'mimes:'.implode(',', self::IMAGE_MIMES), 'max:10240'];
    }

    // Larger images are refused before decoding: a small, highly compressed file can expand to gigabytes in memory
    private const MAX_PIXELS = 50_000_000;

    /**
     * Save an upload under $directory.
     *
     * @param  string  $field  Form field to report an unreadable image on
     * @return array{path: string, mime: string}
     *
     * @throws ValidationException When the image can't be read or is too large
     */
    public function store(UploadedFile $file, string $directory, int $maxEdge = 2000, string $field = 'file'): array
    {
        $name = $directory.'/'.Str::uuid();

        if ($file->getMimeType() === 'application/pdf') {
            $path = $name.'.pdf';
            $this->disk()->put($path, $file->getContent());

            return ['path' => $path, 'mime' => 'application/pdf'];
        }

        $size = @getimagesize($file->getRealPath());

        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                $field => $size ? 'This image is too large. Please upload a smaller one.' : 'This image could not be read. Try a screenshot or a JPG instead.',
            ]);
        }

        try {
            $image = Image::read($file);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => 'This image could not be read. Try a screenshot or a JPG instead.']);
        }

        if (max($image->width(), $image->height()) > $maxEdge) {
            $image->scaleDown(width: $maxEdge, height: $maxEdge);
        }

        $path = $name.'.webp';
        $this->disk()->put($path, (string) $image->toWebp(85));

        return ['path' => $path, 'mime' => 'image/webp'];
    }

    /**
     * Delete a file. A failure is logged, not thrown: the record it belonged to is going anyway.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            $this->disk()->delete($path);
        } catch (Throwable $e) {
            Log::warning('Could not delete a private file', ['path' => $path, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Stream a file to someone already allowed to see it. Never cached by shared caches, and sandboxed so a PDF
     * can't run scripts on the app's origin.
     */
    public function stream(string $path, string $mime, string $downloadName): Response
    {
        $disk = $this->disk();

        try {
            $stream = $disk->readStream($path);
        } catch (Throwable) {
            $stream = null;
        }

        abort_if(! $stream, 404);

        // PDFs are downloaded rather than opened in the tab: browsers won't show a PDF inside the sandbox below, and
        // a PDF opened on the app's own origin is a needless risk
        $isPdf = $mime === 'application/pdf';
        $fileName = (Str::slug($downloadName) ?: 'file').($isPdf ? '.pdf' : '.webp');

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($isPdf ? 'attachment' : 'inline').'; filename="'.$fileName.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.private'));
    }
}
