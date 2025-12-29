<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OlrRace extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'organizer',
        'location',
        'country',
        'year',
        'start_date',
        'end_date',
        'description',
        'website_url',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pigeons(): BelongsToMany
    {
        return $this->belongsToMany(Pigeon::class, 'olr_race_pigeon')
            ->withPivot('entry_number', 'result', 'notes')
            ->withTimestamps();
    }
}
