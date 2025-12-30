<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OlrSeason extends Model
{
    protected $fillable = [
        'olr_race_id',
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

    public function olrRace(): BelongsTo
    {
        return $this->belongsTo(OlrRace::class);
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'olr_season_entries')
            ->withPivot('id', 'entry_number', 'notes')
            ->withTimestamps();
    }

    public function races(): HasMany
    {
        return $this->hasMany(OlrSeasonRace::class);
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
