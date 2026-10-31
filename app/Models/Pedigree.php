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
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
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
