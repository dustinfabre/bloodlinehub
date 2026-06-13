<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubSeasonEvent extends Model
{
    protected $fillable = [
        'club_season_id',
        'name',
        'event_date',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(ClubSeason::class, 'club_season_id');
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'club_season_event_entries')
            ->withPivot('id', 'notes')
            ->withTimestamps();
    }

    public function getEntriesCountAttribute(): int
    {
        return $this->entries()->count();
    }
}
