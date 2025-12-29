<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pigeon extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'gender',
        'hatch_date',
        'status',
        'pigeon_status',
        'race_type',
        'color',
        'ring_number',
        'personal_number',
        'remarks',
        'notes',
        'photos',
        'pedigree_image',
        'for_sale',
        'sale_price',
        'hide_price',
        'sale_description',
        'sire_id',
        'dam_id',
        'sire_name',
        'sire_ring_number',
        'sire_color',
        'sire_notes',
        'dam_name',
        'dam_ring_number',
        'dam_color',
        'dam_notes',
    ];

    protected $casts = [
        'photos' => 'array',
        'hatch_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sire_id');
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dam_id');
    }

    public function olrRaces(): BelongsToMany
    {
        return $this->belongsToMany(OlrRace::class, 'olr_race_pigeon')
            ->withPivot('entry_number', 'result', 'notes')
            ->withTimestamps();
    }

    public function clubRaces(): BelongsToMany
    {
        return $this->belongsToMany(ClubRace::class, 'club_race_pigeon')
            ->withPivot('arrival_time', 'speed', 'position', 'notes')
            ->withTimestamps();
    }
}
