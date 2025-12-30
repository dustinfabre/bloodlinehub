<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubSeason extends Model
{
    protected $fillable = [
        'club_id',
        'name',
        'year',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'year' => 'integer',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'club_season_entries')
            ->withPivot('id', 'entry_number', 'notes')
            ->withTimestamps();
    }

    public function races(): HasMany
    {
        return $this->hasMany(ClubSeasonRace::class);
    }

    public function getEntriesCountAttribute(): int
    {
        return $this->entries()->count();
    }

    public function getRacesCountAttribute(): int
    {
        return $this->races()->count();
    }
}
