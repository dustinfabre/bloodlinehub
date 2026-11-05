<?php

namespace App\Models;

use App\Enums\RaceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubSeasonRace extends Model
{
    protected $fillable = [
        'club_season_id',
        'name',
        'race_type',
        'birds_released',
        'release_point',
        'distance',
        'distance_unit',
        'race_date',
        'release_time',
        'weather_conditions',
        'wind_direction',
        'notes',
    ];

    protected $casts = [
        'race_date' => 'date',
        'distance' => 'decimal:2',
        'race_type' => RaceType::class,
        'birds_released' => 'integer',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(ClubSeason::class, 'club_season_id');
    }

    public function results(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'club_race_results')
            ->withPivot('id', 'position', 'arrival_time', 'speed', 'notes', 'did_not_arrive')
            ->withTimestamps();
    }

    /**
     * Same as results(). Nested routes use it to resolve {pigeon} only among birds with a result in this race (scoped route bindings).
     */
    public function pigeons(): BelongsToMany
    {
        return $this->results();
    }

    public function getArrivedCountAttribute(): int
    {
        return $this->results()->wherePivot('did_not_arrive', false)->whereNotNull('club_race_results.arrival_time')->count();
    }

    public function getTotalEntriesAttribute(): int
    {
        return $this->results()->count();
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->release_point ?? $this->name;
        $distance = $this->distance ? "{$this->distance}{$this->distance_unit}" : '';
        $stats = "{$this->arrived_count}/{$this->total_entries}";
        
        return trim("{$name} {$distance} {$stats}");
    }
}
