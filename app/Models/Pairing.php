<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pairing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sire_id',
        'dam_id',
        'pair_name',
        'status',
        'current_clutch_number',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the user that owns the pairing.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sire (male parent) pigeon.
     */
    public function sire(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class, 'sire_id');
    }

    /**
     * Get the dam (female parent) pigeon.
     */
    public function dam(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class, 'dam_id');
    }

    /**
     * Get all offspring (pigeons) from this pairing.
     */
    public function offspring(): HasMany
    {
        return $this->hasMany(Pigeon::class, 'clutch_id');
    }

    /**
     * Get all clutches from this pairing.
     */
    public function clutches(): HasMany
    {
        return $this->hasMany(Clutch::class);
    }

    /**
     * Scope to get only active pairings.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only inactive pairings.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Check if this pairing is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * End this pairing session.
     */
    public function end(): void
    {
        $this->update([
            'status' => 'inactive',
            'ended_at' => now(),
        ]);
    }
}
