<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OlrRace extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'organizer',
        'location',
        'country',
        'website',
        'description',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(OlrSeason::class);
    }

    public function activeSeasons(): HasMany
    {
        return $this->hasMany(OlrSeason::class)->where('status', 'active');
    }

    public function olrLocation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Location::class);
    }
}
