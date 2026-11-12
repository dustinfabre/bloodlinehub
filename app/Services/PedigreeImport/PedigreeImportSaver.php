<?php

namespace App\Services\PedigreeImport;

use App\Models\Bloodline;
use App\Models\PedigreeImport;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\Lineage\LoftGraph;
use App\Services\Pedigree\OutsideBirdSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a checked pedigree import: the bird itself, its parents and the ancestors behind them.
 *
 * Ancestors are stored the way the pedigree editor stores them: an outside bird on its nearest loft bird, by path
 * from it. Behind a parent that is a loft bird (linked on the review page, or already linked on an existing pigeon),
 * the sheet fills that bird's own pedigree, so its other offspring gain the ancestors too.
 *
 * Nothing already recorded is overwritten. Empty places are filled; a place holding the same bird (same ring
 * number, or same name when a ring is missing) gets the details it lacked; a place holding a different bird is
 * kept, along with everything behind it, and reported. New and completed outside birds are passed to
 * OutsideBirdSync, so other pedigrees with the same bird gain what the sheet adds behind it.
 */
final class PedigreeImportSaver
{
    private const SIDES = ['S' => 'sire', 'D' => 'dam'];

    // Deepest path stored from a loft bird, as in the pedigree editor
    private const MAX_PATH = 4;

    /** @var list<array{0: Pigeon, 1: string, 2: array}> */
    private array $additions = [];

    /** @var list<array{0: Pigeon, 1: string, 2: array, 3: array}> */
    private array $edits = [];

    /** @var list<string> Chart positions where a different bird was already recorded */
    private array $kept = [];

    /** @var array<int, Pigeon> Loft birds whose pedigrees this import wrote to */
    private array $touched = [];

    private int $saved = 0;

    public function __construct(private OutsideBirdSync $sync) {}

    /**
     * @param  array{target: string, pigeon_id?: ?int, bird: array, ancestors?: array, links?: array}  $data  validated
     * @return array{pigeon: Pigeon, ancestors: int, kept: list<string>, also_in: list<string>}
     */
    public function save(PedigreeImport $import, array $data): array
    {
        $this->additions = $this->edits = $this->kept = $this->touched = [];
        $this->saved = 0;

        return DB::transaction(function () use ($import, $data) {
            $pigeon = $data['target'] === 'existing'
                ? $this->completeSubject(Pigeon::where('user_id', $import->user_id)->findOrFail($data['pigeon_id']), $data['bird'])
                : $this->createSubject($import->user_id, $data['bird']);

            $this->addMerits($pigeon, $data['bird']['merits'] ?? null);
            $this->link($pigeon, $data['links'] ?? []);
            $this->fill($pigeon, self::details($data['ancestors'] ?? []), '');

            $alsoIn = [];
            foreach ($this->additions as [$anchor, $path, $details]) {
                $alsoIn = [...$alsoIn, ...$this->sync->pushAddition($anchor, $path, $details)['updated']];
            }
            foreach ($this->edits as [$anchor, $path, $before, $after]) {
                $alsoIn = [...$alsoIn, ...$this->sync->pushEdit($anchor, $path, $before, $after)['updated']];
            }

            // Only other pedigrees: the ones this import wrote to are the point of it
            $own = array_map(self::label(...), $this->touched);

            return [
                'pigeon' => $pigeon,
                'ancestors' => $this->saved,
                'kept' => $this->kept,
                'also_in' => array_values(array_diff(array_unique($alsoIn), $own)),
            ];
        });
    }

    /**
     * The loft bird with this ring number (compared the way Lineage compares rings), if any.
     */
    public static function loftBirdByRing(int $userId, ?string $ring, ?int $exceptId = null): ?Pigeon
    {
        $key = LoftGraph::normalizeRing($ring);

        if (! $key) {
            return null;
        }

        $id = Pigeon::where('user_id', $userId)
            ->whereNotNull('ring_number')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get(['id', 'ring_number'])
            ->first(fn (Pigeon $pigeon) => LoftGraph::normalizeRing($pigeon->ring_number) === $key)
            ?->id;

        return $id ? Pigeon::find($id) : null;
    }

    private function createSubject(int $userId, array $bird): Pigeon
    {
        $ring = strtoupper(trim((string) $bird['ring_number']));

        if ($existing = self::loftBirdByRing($userId, $ring)) {
            throw ValidationException::withMessages([
                'bird.ring_number' => "{$existing->ring_number} is already in your loft. Choose to add this pedigree to that bird, or correct the ring number.",
            ]);
        }

        $pigeon = Pigeon::create([
            'user_id' => $userId,
            'ring_number' => $ring,
            'name' => filled($bird['name'] ?? null) ? strtoupper($bird['name']) : null,
            'gender' => $bird['gender'] ?? null,
            'color' => $bird['color'] ?? null,
            'bred_by' => $bird['bred_by'] ?? null,
            'remarks' => $bird['remarks'] ?? null,
            'status' => $bird['status'] ?? 'stock',
        ]);

        $this->setBloodline($pigeon, $bird['bloodline'] ?? null);
        $this->touched[$pigeon->id] = $pigeon;

        return $pigeon;
    }

    /**
     * An existing bird gets the details it doesn't have yet; what is already recorded stays.
     */
    private function completeSubject(Pigeon $pigeon, array $bird): Pigeon
    {
        $missing = [];

        foreach (['name', 'gender', 'color', 'bred_by', 'remarks'] as $field) {
            if (blank($pigeon->{$field}) && filled($bird[$field] ?? null)) {
                $missing[$field] = $field === 'name' ? strtoupper($bird[$field]) : $bird[$field];
            }
        }

        if ($missing) {
            $pigeon->update($missing);
        }

        if (blank($pigeon->bloodline) && ! $pigeon->bloodlines()->exists()) {
            $this->setBloodline($pigeon, $bird['bloodline'] ?? null);
        }

        $this->touched[$pigeon->id] = $pigeon;

        return $pigeon;
    }

    private function setBloodline(Pigeon $pigeon, ?string $name): void
    {
        $name = strtoupper(trim((string) $name));

        if ($name === '') {
            return;
        }

        $bloodline = Bloodline::firstOrCreate(['user_id' => $pigeon->user_id, 'name' => $name]);
        $pigeon->bloodlines()->syncWithoutDetaching([$bloodline->id => ['is_primary' => true]]);
        $pigeon->update(['bloodline' => $bloodline->name]);
    }

    /**
     * Merits the sheet gives the bird itself, leaving out ones it already has. Saved like merits the owner adds,
     * since the owner checked them.
     */
    private function addMerits(Pigeon $pigeon, ?array $merits): void
    {
        $key = fn (?string $type, ?string $level, ?int $year) => "{$type}|{$level}|{$year}";
        $existing = $pigeon->merits()->get()
            ->map(fn (PigeonMerit $merit) => $key($merit->type->value, $merit->level?->value, $merit->year))
            ->all();

        foreach (PigeonMerit::cleanList($merits) ?? [] as $merit) {
            if (in_array($key($merit['type'], $merit['level'], $merit['year']), $existing, true)) {
                continue;
            }

            PigeonMerit::create(['pigeon_id' => $pigeon->id, ...$merit]);
            $existing[] = $key($merit['type'], $merit['level'], $merit['year']);
        }
    }

    /**
     * Link parents that are in the loft, where nothing is recorded on that side yet.
     *
     * @param  array<string, ?int>  $links  side letter => loft bird id
     */
    private function link(Pigeon $pigeon, array $links): void
    {
        foreach (self::SIDES as $letter => $side) {
            $id = $links[$letter] ?? null;

            if (! $id || $pigeon->{"{$side}_id"} || $pigeon->hasExternalParent($side)) {
                continue;
            }

            $parent = Pigeon::where('user_id', $pigeon->user_id)->findOrFail($id);

            if ($parent->id === $pigeon->id || in_array($pigeon->id, self::ancestorIds($parent), true)) {
                throw ValidationException::withMessages([
                    "links.{$letter}" => "{$parent->ring_number} descends from this pigeon, so it can't be its {$side}.",
                ]);
            }

            // Like the pigeon form, keep a copy of the parent's name, ring and color
            $pigeon->update([
                "{$side}_id" => $parent->id,
                "{$side}_name" => $parent->name,
                "{$side}_ring_number" => $parent->ring_number,
                "{$side}_color" => $parent->color,
            ]);
        }
    }

    /**
     * Place the birds of the sheet behind $anchor. $birds are keyed by path from $anchor; $prefix is $anchor's own
     * position on the sheet ("" for the bird itself), to report places by their position on the sheet.
     *
     * @param  array<string, array>  $birds
     */
    private function fill(Pigeon $anchor, array $birds, string $prefix): void
    {
        foreach (self::SIDES as $letter => $side) {
            $branch = array_filter($birds, fn ($path) => str_starts_with((string) $path, $letter), ARRAY_FILTER_USE_KEY);

            if (! $branch) {
                continue;
            }

            // A loft bird brings its own pedigree: fill the gaps in it
            if ($anchor->{"{$side}_id"}) {
                $parent = Pigeon::where('user_id', $anchor->user_id)->find($anchor->{"{$side}_id"});
                $behind = [];

                foreach ($branch as $path => $bird) {
                    if (strlen((string) $path) > 1) {
                        $behind[substr((string) $path, 1)] = $bird;
                    }
                }

                if ($parent && $behind) {
                    $this->fill($parent, $behind, $prefix.$letter);
                }

                continue;
            }

            $this->fillOutside($anchor, $branch, $prefix);
        }
    }

    /**
     * @param  array<string, array>  $branch  outside birds on one side of $anchor, by path from it
     */
    private function fillOutside(Pigeon $anchor, array $branch, string $prefix): void
    {
        // The bird in front of an ancestor has to be placed first
        uksort($branch, fn ($a, $b) => [strlen((string) $a), (string) $a] <=> [strlen((string) $b), (string) $b]);
        $blocked = [];

        foreach ($branch as $path => $details) {
            $path = (string) $path;

            if (strlen($path) > self::MAX_PATH) {
                continue;
            }

            foreach ($blocked as $closed) {
                if (str_starts_with($path, $closed)) {
                    continue 2;
                }
            }

            $before = $this->sync->detailsAt($anchor, $path);

            if ($before === null) {
                if (strlen($path) > 1 && $this->sync->detailsAt($anchor, substr($path, 0, -1)) === null) {
                    $blocked[] = $path;

                    continue;
                }

                $this->write($anchor, $path, $details);
                $this->additions[] = [$anchor, $path, $details];
                $this->saved++;
            } elseif (self::sameBird($before, $details)) {
                $after = $before;

                foreach ($details as $field => $value) {
                    if (blank($before[$field] ?? null) && filled($value)) {
                        $after[$field] = $value;
                    }
                }

                if ($after != $before) {
                    $this->write($anchor, $path, $after);
                    $this->edits[] = [$anchor, $path, $before, $after];
                }
            } else {
                $blocked[] = $path;
                $this->kept[] = $prefix.$path;
            }
        }
    }

    private function write(Pigeon $anchor, string $path, array $details): void
    {
        $this->touched[$anchor->id] = $anchor;

        if (strlen($path) === 1) {
            $side = self::SIDES[$path];
            $anchor->update(collect(OutsideBirdSync::FIELDS)
                ->mapWithKeys(fn (string $column, string $field) => ["{$side}_{$column}" => $details[$field] ?? null])
                ->all());

            return;
        }

        $anchor->pedigreeAncestors()->updateOrCreate(['path' => $path], $details);
    }

    /**
     * Two records of a place are the same bird when their ring numbers match, or, when either has no ring, their
     * names do.
     */
    private static function sameBird(array $a, array $b): bool
    {
        $ringA = LoftGraph::normalizeRing($a['ring_number'] ?? null);
        $ringB = LoftGraph::normalizeRing($b['ring_number'] ?? null);

        if ($ringA && $ringB) {
            return $ringA === $ringB;
        }

        $nameA = mb_strtolower(trim((string) ($a['name'] ?? '')));

        return $nameA !== '' && $nameA === mb_strtolower(trim((string) ($b['name'] ?? '')));
    }

    /**
     * Stored details of each ancestor, by path.
     *
     * @return array<string, array{ring_number: ?string, name: ?string, color: ?string, bloodline: ?string, remarks: ?string, merits: ?array}>
     */
    private static function details(array $ancestors): array
    {
        $details = [];

        foreach ($ancestors as $path => $bird) {
            $details[(string) $path] = [
                'ring_number' => filled($bird['ring_number'] ?? null) ? strtoupper(trim($bird['ring_number'])) : null,
                'name' => $bird['name'] ?? null,
                'color' => $bird['color'] ?? null,
                'bloodline' => $bird['bloodline'] ?? null,
                'remarks' => $bird['remarks'] ?? null,
                'merits' => PigeonMerit::cleanList($bird['merits'] ?? null),
            ];
        }

        return $details;
    }

    /**
     * Ids of a loft bird's linked ancestors, to keep a link from making a bird its own ancestor.
     *
     * @return list<int>
     */
    private static function ancestorIds(Pigeon $pigeon): array
    {
        $found = [];
        $frontier = array_filter([$pigeon->sire_id, $pigeon->dam_id]);

        for ($generation = 0; $generation < 30 && $frontier; $generation++) {
            $found = [...$found, ...$frontier];
            $frontier = Pigeon::whereIn('id', $frontier)
                ->get(['sire_id', 'dam_id'])
                ->flatMap(fn (Pigeon $parent) => [$parent->sire_id, $parent->dam_id])
                ->filter(fn ($id) => $id && ! in_array($id, $found, true))
                ->unique()
                ->values()
                ->all();
        }

        return $found;
    }

    private static function label(Pigeon $pigeon): string
    {
        return $pigeon->ring_number ?: $pigeon->name ?: $pigeon->personal_number ?: "Pigeon #{$pigeon->id}";
    }
}
