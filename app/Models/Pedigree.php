<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pedigree extends Model
{
    // Unambiguous alphabet (no 0/O, 1/I/L or U) so serials are easy to read aloud and type.
    private const SERIAL_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    protected $fillable = [
        'pigeon_id',
        'serial',
        'is_public',
        'password',
        'password_expires_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'password' => 'hashed',
        'password_expires_at' => 'datetime',
    ];

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }

    public function hasActivePassword(): bool
    {
        return $this->password !== null
            && ($this->password_expires_at === null || $this->password_expires_at->isFuture());
    }

    /**
     * 'public' (anyone with the link), 'password' (anyone with the link and password) or 'private' (owner only).
     */
    public function visibility(): string
    {
        if ($this->is_public) {
            return 'public';
        }

        return $this->hasActivePassword() ? 'password' : 'private';
    }

    /**
     * Kept in the visitor's session after they enter the password. Derived from the password hash,
     * so changing or removing the password ends access for everyone who unlocked it before.
     */
    public function accessToken(): string
    {
        return hash_hmac('sha256', $this->id.'|'.$this->password, config('app.key'));
    }

    public function grantsAccess(?string $token): bool
    {
        return $token !== null && $this->hasActivePassword() && hash_equals($this->accessToken(), $token);
    }

    /**
     * Generate a unique serial such as "BLH-7K4Q-9XMP".
     */
    public static function generateSerial(): string
    {
        do {
            $chars = '';
            for ($i = 0; $i < 8; $i++) {
                $chars .= self::SERIAL_ALPHABET[random_int(0, strlen(self::SERIAL_ALPHABET) - 1)];
            }
            $serial = 'BLH-'.substr($chars, 0, 4).'-'.substr($chars, 4, 4);
        } while (static::where('serial', $serial)->exists());

        return $serial;
    }
}
