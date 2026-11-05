<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An image stored in its owner's Google Drive and served by the app at /media/{id}.webp.
 */
class DriveFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'drive_file_id',
        'mime_type',
        'size',
    ];

    /**
     * A random (v4) UUID rather than the default time-ordered one: the id is the only secret in a public media URL.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relative, so stored URLs keep working if the app moves to another domain.
     */
    public function url(): string
    {
        return route('media.show', ['file' => $this->id], absolute: false);
    }

    /**
     * The DriveFile id inside a URL produced by url(), or null for anything else (such as an old external image URL).
     */
    public static function idFromUrl(?string $url): ?string
    {
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;

        if (is_string($path) && preg_match('#^/media/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.webp$#i', $path, $match)) {
            return strtolower($match[1]);
        }

        return null;
    }
}
