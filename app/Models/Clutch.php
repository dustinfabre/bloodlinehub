<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clutch extends Model
{
    protected $fillable = [
        'pairing_id',
        'clutch_number',
        'eggs_laid_date',
        'hatched_date',
        'status',
        'notes',
        'is_fostered',
        'biological_pairing_id',
    ];

    protected $casts = [
        'eggs_laid_date' => 'date',
        'hatched_date' => 'date',
        'is_fostered' => 'boolean',
    ];

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(Pairing::class);
    }

    /**
     * Get the biological parents (when this is a fostered clutch).
     */
    public function biologicalParents(): BelongsTo
    {
        return $this->belongsTo(Pairing::class, 'biological_pairing_id');
    }

    public function offspring(): HasMany
    {
        return $this->hasMany(Pigeon::class, 'clutch_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isUnsuccessful(): bool
    {
        return $this->status === 'unsuccessful';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsSuccessful(): void
    {
        $this->update(['status' => 'successful']);
    }

    public function markAsUnsuccessful(): void
    {
        $this->update(['status' => 'unsuccessful']);
    }
}
