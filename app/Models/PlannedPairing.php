<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A test pair saved to the breeding plan, before it becomes a real pairing.
 */
class PlannedPairing extends Model
{
    protected $fillable = [
        'user_id',
        'sire_id',
        'dam_id',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class, 'sire_id');
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class, 'dam_id');
    }
}
