<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pigeon extends Model
{
    use HasFactory;

    public const STATUSES = ['stock', 'racing', 'breeding', 'injured', 'deceased', 'missing'];

    // Birds with these statuses are no longer in the loft: lists and pickers leave them out unless asked for
    public const GONE_STATUSES = ['deceased', 'missing'];

    // In stock, racing, breeding and injured: the birds actually in the loft
    public const IN_LOFT_STATUSES = ['stock', 'racing', 'breeding', 'injured'];

    public const STATUS_LABELS = [
        'stock' => 'In Stock',
        'racing' => 'Racing',
        'breeding' => 'Breeding',
        'injured' => 'Injured',
        'deceased' => 'Deceased',
        'missing' => 'Missing',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'bloodline',
        'gender',
        'hatch_date',
        'status',
        'color',
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
        'sire_bloodline',
        'sire_notes',
        'sire_merits',
        'dam_name',
        'dam_ring_number',
        'dam_color',
        'dam_bloodline',
        'dam_notes',
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

    protected static function booted(): void
    {
        // Keep a history of status changes, so the app knows when a bird went missing or came back. Loft checks
        // change many birds with one query and record their own changes.
        static::updated(function (Pigeon $pigeon) {
            if ($pigeon->wasChanged('status')) {
                $pigeon->statusChanges()->create([
                    'from_status' => $pigeon->getOriginal('status'),
                    'to_status' => $pigeon->status,
                ]);
            }
        });

        // While a parent is linked, {side}_notes on its offspring are private notes about it. Once the parent is
        // gone those fields would be read as the remarks of an external parent and shown on the pedigree, so move
        // them into each offspring's private notes first.
        static::deleting(function (Pigeon $parent) {
            foreach (['sire', 'dam'] as $side) {
                $children = self::query()
                    ->where("{$side}_id", $parent->id)
                    ->whereNotNull("{$side}_notes")
                    ->where("{$side}_notes", '!=', '')
                    ->get(['id', 'notes', "{$side}_notes"]);

                foreach ($children as $child) {
                    $label = $parent->ring_number ?: $parent->name ?: "#{$parent->id}";
                    $moved = "Notes about former {$side} {$label} (removed from the loft):\n".$child->{"{$side}_notes"};

                    self::whereKey($child->id)->update([
                        'notes' => trim(($child->notes ? $child->notes."\n\n" : '').$moved),
                        "{$side}_notes" => null,
                    ]);
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only birds still in the loft (not deceased or missing).
     */
    public function scopeInLoft(Builder $query): void
    {
        $query->whereNotIn($query->qualifyColumn('status'), self::GONE_STATUSES);
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

    // Columns and relations searchSummary() reads; select and eager load these for search results
    public const SEARCH_COLUMNS = [
        'id', 'user_id', 'name', 'ring_number', 'personal_number', 'gender', 'color', 'bloodline', 'notes', 'remarks',
        'sire_id', 'dam_id', 'sire_name', 'sire_ring_number', 'dam_name', 'dam_ring_number',
    ];

    public const SEARCH_RELATIONS = ['sire:id,name,ring_number', 'dam:id,name,ring_number'];

    /**
     * The standard details of a pigeon in every pigeon search and picker, shown by PigeonSearchResult.vue
     * (see "Pigeon search results" in CLAUDE.md). Includes private notes, so only send it to the bird's owner.
     *
     * @return array{id: int, name: ?string, ring_number: ?string, personal_number: ?string, gender: ?string, color: ?string, bloodline: ?string, notes: ?string, remarks: ?string, sire: ?array{ring_number: ?string, name: ?string}, dam: ?array{ring_number: ?string, name: ?string}}
     */
    public function searchSummary(): array
    {
        // A linked loft parent, or else the outside parent recorded on this pigeon
        $parent = function (string $side): ?array {
            $linked = $this->{"{$side}_id"} ? $this->{$side} : null;
            $ring = $linked ? $linked->ring_number : $this->{"{$side}_ring_number"};
            $name = $linked ? $linked->name : $this->{"{$side}_name"};

            return $ring || $name ? ['ring_number' => $ring, 'name' => $name] : null;
        };

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ring_number' => $this->ring_number,
            'personal_number' => $this->personal_number,
            'gender' => $this->gender,
            'color' => $this->color,
            'bloodline' => $this->bloodline,
            'notes' => $this->notes,
            'remarks' => $this->remarks,
            'sire' => $parent('sire'),
            'dam' => $parent('dam'),
        ];
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

    // Status changes, most recent first
    public function statusChanges(): HasMany
    {
        return $this->hasMany(PigeonStatusChange::class)->orderByDesc('id');
    }

    public function latestStatusChange(): HasOne
    {
        return $this->hasOne(PigeonStatusChange::class)->latestOfMany();
    }

    /**
     * When the bird got its current status, if that change was recorded (e.g. when it went missing). Needs
     * latestStatusChange loaded or loads it.
     */
    public function statusSince(): ?\Illuminate\Support\Carbon
    {
        $latest = $this->latestStatusChange;

        return $latest && $latest->to_status === $this->status ? $latest->changed_at : null;
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
