<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A count of the birds in part of the loft, e.g. after a training toss: which birds were seen, which were marked
 * missing, and which missing birds came back.
 */
class LoftCheck extends Model
{
    // What happened to each bird in a check (loft_check_pigeon.result)
    public const PRESENT = 'present';

    public const MISSING = 'missing';

    public const UNCHECKED = 'unchecked';

    public const RETURNED = 'returned';

    protected $fillable = [
        'user_id',
        'label',
        'scope',
        'note',
        'expected_count',
        'present_count',
        'missing_count',
        'unchecked_count',
        'returned_count',
    ];

    protected $casts = [
        'scope' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pigeons(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class)->withPivot('result');
    }

    // The birds this check marked missing
    public function missingPigeons(): BelongsToMany
    {
        return $this->pigeons()->wherePivot('result', self::MISSING);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(PigeonStatusChange::class);
    }
}
