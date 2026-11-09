<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change of a pigeon's status. Recorded by Pigeon whenever its status is saved, and by loft checks, which
 * change many birds at once.
 */
class PigeonStatusChange extends Model
{
    public const CREATED_AT = 'changed_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'pigeon_id',
        'loft_check_id',
        'from_status',
        'to_status',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }

    public function loftCheck(): BelongsTo
    {
        return $this->belongsTo(LoftCheck::class);
    }
}
