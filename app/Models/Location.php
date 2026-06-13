<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'olr_race_id',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function olrRace(): BelongsTo
    {
        return $this->belongsTo(OlrRace::class);
    }

    public function pigeons(): HasMany
    {
        return $this->hasMany(Pigeon::class);
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(Pairing::class, 'breeding_location_id');
    }

    public function clutches(): HasMany
    {
        return $this->hasMany(Clutch::class, 'success_location_id');
    }

    public function isOlr(): bool
    {
        return $this->type === 'olr';
    }

    public function isManual(): bool
    {
        return $this->type === 'manual';
    }
}
