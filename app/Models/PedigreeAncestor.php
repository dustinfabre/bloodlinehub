<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ancestor that is not a bird in the loft, recorded on the nearest loft bird ($pigeon).
 * $path is relative to that bird, e.g. "SD" for its sire's dam.
 */
class PedigreeAncestor extends Model
{
    protected $fillable = [
        'pigeon_id',
        'path',
        'ring_number',
        'name',
        'color',
        'remarks',
        'color_tag_id',
        'merits',
    ];

    protected $casts = [
        'merits' => 'array',
    ];

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }

    public function colorTag(): BelongsTo
    {
        return $this->belongsTo(ColorTag::class);
    }
}
