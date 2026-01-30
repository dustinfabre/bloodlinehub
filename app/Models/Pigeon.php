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
        'bloodline',
        'gender',
        'hatch_date',
        'status',
        'color',
        'color_tag_id',
        'ring_number',
        'personal_number',
        'remarks',
        'notes',
        'photo_url',
        'pedigree_images',
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
        'pairing_id',
        'clutch_id',
    ];

    protected $casts = [
        'pedigree_images' => 'array',
        'hatch_date' => 'date',
        'for_sale' => 'boolean',
        'hide_price' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function colorTag(): BelongsTo
    {
        return $this->belongsTo(ColorTag::class);
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sire_id');
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dam_id');
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(Pairing::class);
    }

    public function clutch(): BelongsTo
    {
        return $this->belongsTo(Clutch::class);
    }

    // OLR Season Entries
    public function olrSeasonEntries(): BelongsToMany
    {
        return $this->belongsToMany(OlrSeason::class, 'olr_season_entries')
            ->withPivot('id', 'entry_number', 'notes')
            ->withTimestamps();
    }

    // OLR Race Results
    public function olrRaceResults(): BelongsToMany
    {
        return $this->belongsToMany(OlrSeasonRace::class, 'olr_race_results')
            ->withPivot('id', 'position', 'arrival_time', 'speed', 'notes', 'did_not_arrive')
            ->withTimestamps();
    }

    // Club Season Entries
    public function clubSeasonEntries(): BelongsToMany
    {
        return $this->belongsToMany(ClubSeason::class, 'club_season_entries')
            ->withPivot('id', 'entry_number', 'notes')
            ->withTimestamps();
    }

    // Club Race Results
    public function clubRaceResults(): BelongsToMany
    {
        return $this->belongsToMany(ClubSeasonRace::class, 'club_race_results')
            ->withPivot('id', 'position', 'arrival_time', 'speed', 'notes', 'did_not_arrive')
            ->withTimestamps();
    }

    // Bloodlines (many-to-many with primary flag)
    public function bloodlines(): BelongsToMany
    {
        return $this->belongsToMany(Bloodline::class, 'pigeon_bloodline')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    // Get the primary bloodline (convenience accessor)
    public function getPrimaryBloodlineAttribute(): ?Bloodline
    {
        return $this->bloodlines->firstWhere('pivot.is_primary', true);
    }

    // Get primary bloodline name for display
    public function getPrimaryBloodlineNameAttribute(): ?string
    {
        return $this->primaryBloodline?->name ?? $this->bloodline;
    }
}
