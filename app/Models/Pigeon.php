<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pigeon extends Model
{
    use HasFactory;

    public const STATUSES = ['stock', 'racing', 'breeding', 'injured', 'deceased', 'flyaway', 'missing'];

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
        'sire_color_tag_id',
        'sire_merits',
        'dam_name',
        'dam_ring_number',
        'dam_color',
        'dam_notes',
        'dam_color_tag_id',
        'dam_merits',
        'clutch_id',
        'location_id',
    ];

    protected $casts = [
        'pedigree_images' => 'array',
        'hatch_date' => 'date',
        'for_sale' => 'boolean',
        'hide_price' => 'boolean',
        'sire_merits' => 'array',
        'dam_merits' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function colorTag(): BelongsTo
    {
        return $this->belongsTo(ColorTag::class);
    }

    // Pedigree highlight colors for external (unlinked) parents
    public function sireColorTag(): BelongsTo
    {
        return $this->belongsTo(ColorTag::class, 'sire_color_tag_id');
    }

    public function damColorTag(): BelongsTo
    {
        return $this->belongsTo(ColorTag::class, 'dam_color_tag_id');
    }

    public function pedigree(): HasOne
    {
        return $this->hasOne(Pedigree::class);
    }

    // Ancestors beyond the parents that are not in the loft, keyed by path relative to this bird
    public function pedigreeAncestors(): HasMany
    {
        return $this->hasMany(PedigreeAncestor::class);
    }

    // Whether the sire or dam is an external bird recorded on this pigeon (rather than linked or unknown)
    public function hasExternalParent(string $side): bool
    {
        if ($this->{"{$side}_id"}) {
            return false;
        }

        return (bool) ($this->{"{$side}_name"} || $this->{"{$side}_ring_number"} || $this->{"{$side}_color"} || $this->{"{$side}_notes"});
    }

    // Get the pedigree record, creating it (with a new serial) on first use
    public function pedigreeRecord(): Pedigree
    {
        if (! $this->pedigree) {
            $this->setRelation('pedigree', $this->pedigree()->createOrFirst([], [
                'serial' => Pedigree::generateSerial(),
                'is_public' => false,
            ]));
        }

        return $this->pedigree;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // Standard merits (fixed vocabulary), most recent first; merits without a year last
    public function merits(): HasMany
    {
        return $this->hasMany(PigeonMerit::class)
            ->orderByRaw('year IS NULL')
            ->orderByDesc('year')
            ->orderBy('id');
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
            ->withPivot('id', 'entry_number', 'notes', 'overall_position')
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
            ->withPivot('id', 'entry_number', 'notes', 'overall_position')
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
