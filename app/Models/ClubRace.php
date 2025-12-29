<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubRace extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'club_name',
        'release_point',
        'distance',
        'distance_unit',
        'race_date',
        'release_time',
        'description',
        'weather_conditions',
        'wind_direction',
        'status',
    ];

    protected $casts = [
        'race_date' => 'date',
        'distance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pigeons(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'club_race_pigeon')
            ->withPivot('arrival_time', 'speed', 'position', 'notes')
            ->withTimestamps();
    }
}
