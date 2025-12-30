<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OlrSeasonRace extends Model
{
    protected $fillable = [
        'olr_season_id',
        'name',
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
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(OlrSeason::class, 'olr_season_id');
    }

    public function results(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'olr_race_results')
            ->withPivot('id', 'position', 'arrival_time', 'speed', 'notes', 'did_not_arrive')
            ->withTimestamps();
    }

    public function getArrivedCountAttribute(): int
    {
        return $this->results()->wherePivot('did_not_arrive', false)->whereNotNull('olr_race_results.arrival_time')->count();
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
