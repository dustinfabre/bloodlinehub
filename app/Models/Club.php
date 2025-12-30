<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'user_id',
        'name',
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
        return $this->hasMany(ClubSeason::class);
    }

    public function activeSeasons(): HasMany
    {
        return $this->hasMany(ClubSeason::class)->where('status', 'active');
    }
}
