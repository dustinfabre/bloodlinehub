<?php

namespace App\Services\Pedigree;

use App\Models\PedigreeAncestor;
use App\Models\Pigeon;
use App\Services\Lineage\LoftGraph;
use Illuminate\Support\Collection;

/**
 * Keeps the records of one outside bird in step across a loft's pedigrees.
 *
 * A bird that is not in the loft is recorded once per pedigree it appears in, relative to the nearest loft bird
 * (the anchor): as the anchor's external parent (sire_* / dam_* columns, path "S" or "D") or as a pedigree_ancestors
 * row (path "SD", "SDS", ...). Records with the same ring number, compared the way Lineage compares them (ignoring
 * case, spaces and punctuation), are the same bird, so a change saved on one record is applied to the others.
 * Birds without a ring number can't be matched and are never synced.
 */
final class OutsideBirdSync
{
    /** Details of an outside bird => the column suffix on pigeons for an external parent (sire_name, dam_notes, ...). */
    public const FIELDS = [
        'ring_number' => 'ring_number',
        'name' => 'name',
        'color' => 'color',
        'bloodline' => 'bloodline',
        'remarks' => 'notes',
        'merits' => 'merits',
    ];

    /** Deepest path the pedigree editor stores from an anchor (its great-great-grandparents). */
    private const MAX_PATH = 4;

    private const SIDES = ['sire' => 'S', 'dam' => 'D'];

    /**
     * Details of the outside bird recorded at $path on $anchor, or null if nothing is recorded there.
     *
     * @return array<string, mixed>|null
     */
    public function detailsAt(Pigeon $anchor, string $path): ?array
    {
        if (strlen($path) === 1) {
            $side = $this->side($path);

            if (! $anchor->hasExternalParent($side)) {
                return null;
            }

            return collect(self::FIELDS)
                ->mapWithKeys(fn (string $column, string $field) => [$field => $anchor->{"{$side}_{$column}"}])
                ->all();
        }

        return $anchor->pedigreeAncestors()->where('path', $path)->first()?->only(array_keys(self::FIELDS));
    }

    /**
     * Apply the fields that changed between $before and $after to every other record of the same bird.
     * Only changed fields are sent, so a correction made earlier on another record is not overwritten.
     *
     * @return array{updated: list<string>, skipped: list<string>}
     */
    public function pushEdit(Pigeon $anchor, string $path, array $before, array $after): array
    {
        $key = LoftGraph::normalizeRing($before['ring_number'] ?? null);
        $changes = [];

        foreach (array_keys(self::FIELDS) as $field) {
            // Loose comparison: an empty string, null and an empty merit list all mean "nothing recorded"
            if (array_key_exists($field, $after) && ($before[$field] ?? null) != $after[$field]) {
                $changes[$field] = $after[$field];
            }
        }

        if (! $key || ! $changes) {
            return self::report();
        }

        $updated = [];

        foreach ($this->others($anchor->user_id, $key, $anchor->id, $path) as $record) {
            $this->write($record, $changes);
            $updated[] = $record['anchor'];
        }

        return self::report($updated);
    }

    /**
     * A bird was added at $path behind a shared outside bird: add it behind every other record of that bird too.
     * Where another pedigree already has a different bird in that place, it is left alone and reported.
     *
     * @return array{updated: list<string>, skipped: list<string>}
     */
    public function pushAddition(Pigeon $anchor, string $path, array $details): array
    {
        [$key, $letter] = $this->offspringOf($anchor, $path);

        if (! $key) {
            return self::report();
        }

        $newKey = LoftGraph::normalizeRing($details['ring_number'] ?? null);
        $updated = $skipped = [];

        foreach ($this->others($anchor->user_id, $key, $anchor->id, substr($path, 0, -1)) as $record) {
            $target = $record['path'].$letter;

            // Too far back to show on that bird's pedigree
            if (strlen($target) > self::MAX_PATH) {
                continue;
            }

            $existing = $record['anchor']->pedigreeAncestors()->where('path', $target)->first();

            if (! $existing) {
                $record['anchor']->pedigreeAncestors()->create(['path' => $target, ...array_intersect_key($details, self::FIELDS)]);
                $updated[] = $record['anchor'];
            } elseif (! $newKey || LoftGraph::normalizeRing($existing->ring_number) !== $newKey) {
                $skipped[] = $record['anchor'];
            }
        }

        return self::report($updated, $skipped);
    }

    /**
     * The bird with ring $removedRing was removed from $path behind a shared outside bird: remove it, and what is
     * recorded behind it, wherever the same bird has it in the same place.
     *
     * @return array{updated: list<string>, skipped: list<string>}
     */
    public function pushRemoval(Pigeon $anchor, string $path, ?string $removedRing): array
    {
        [$key, $letter] = $this->offspringOf($anchor, $path);
        $removedKey = LoftGraph::normalizeRing($removedRing);

        if (! $key || ! $removedKey) {
            return self::report();
        }

        $updated = [];

        foreach ($this->others($anchor->user_id, $key, $anchor->id, substr($path, 0, -1)) as $record) {
            $target = $record['path'].$letter;
            $existing = $record['anchor']->pedigreeAncestors()->where('path', $target)->first();

            if ($existing && LoftGraph::normalizeRing($existing->ring_number) === $removedKey) {
                $record['anchor']->pedigreeAncestors()->where('path', 'like', $target.'%')->delete();
                $updated[] = $record['anchor'];
            }
        }

        return self::report($updated);
    }

    /**
     * For each ring in $rings: every record of that bird in the loft, labelled with the loft bird whose pedigree
     * holds it, so the editor can say where a change will also apply.
     *
     * @param  iterable<?string>  $rings
     * @return array<string, list<array{anchor_id: int, path: string, label: string}>> keyed by normalised ring
     */
    public function recordsFor(int $userId, iterable $rings): array
    {
        $keys = collect($rings)->map(fn (?string $ring) => LoftGraph::normalizeRing($ring))->filter()->unique();

        if ($keys->isEmpty()) {
            return [];
        }

        $records = $this->records($userId);

        return $keys->mapWithKeys(fn (string $key) => [$key => array_map(fn (array $record) => [
            'anchor_id' => $record['anchor']->id,
            'path' => $record['path'],
            'label' => self::label($record['anchor']),
        ], $records[$key] ?? [])])->filter()->all();
    }

    /**
     * Labels of the loft birds whose pedigrees hold another record of the bird with ring $ring.
     *
     * @return list<string>
     */
    public function otherLabels(int $userId, ?string $ring, int $anchorId, string $path): array
    {
        $key = LoftGraph::normalizeRing($ring);

        return $key
            ? self::report(array_column($this->others($userId, $key, $anchorId, $path), 'anchor'))['updated']
            : [];
    }

    /**
     * @return list<array{anchor: Pigeon, path: string, row: ?PedigreeAncestor}>
     */
    private function others(int $userId, string $key, int $anchorId, string $path): array
    {
        return array_values(array_filter(
            $this->records($userId)[$key] ?? [],
            fn (array $record) => ! ($record['anchor']->id === $anchorId && $record['path'] === $path),
        ));
    }

    /**
     * Every outside bird record in the loft that shows on a pedigree, grouped by normalised ring number.
     * Records hidden from every pedigree (behind a parent that has since been linked to a loft bird, or behind a
     * removed bird) are left out.
     *
     * @return array<string, list<array{anchor: Pigeon, path: string, row: ?PedigreeAncestor}>>
     */
    private function records(int $userId): array
    {
        $pigeons = Pigeon::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get([
                'id', 'user_id', 'name', 'ring_number', 'personal_number', 'sire_id', 'dam_id',
                ...collect(self::SIDES)->keys()->crossJoin(self::FIELDS)->map(fn (array $pair) => implode('_', $pair))->all(),
            ]);

        $rows = PedigreeAncestor::query()
            ->whereIn('pigeon_id', $pigeons->modelKeys())
            ->get(['id', 'pigeon_id', 'path', 'ring_number'])
            ->groupBy('pigeon_id');

        $records = [];

        foreach ($pigeons as $pigeon) {
            foreach (self::SIDES as $side => $letter) {
                if ($pigeon->hasExternalParent($side) && ($key = LoftGraph::normalizeRing($pigeon->{"{$side}_ring_number"}))) {
                    $records[$key][] = ['anchor' => $pigeon, 'path' => $letter, 'row' => null];
                }
            }

            $byPath = ($rows->get($pigeon->id) ?? collect())->keyBy('path');

            foreach ($byPath as $path => $row) {
                if (($key = LoftGraph::normalizeRing($row->ring_number)) && $this->shown($pigeon, (string) $path, $byPath)) {
                    $records[$key][] = ['anchor' => $pigeon, 'path' => (string) $path, 'row' => $row];
                }
            }
        }

        return $records;
    }

    /**
     * Whether a pedigree_ancestors row is reachable: the anchor's parent on that side is external and every bird
     * between the anchor and the row is recorded.
     *
     * @param  Collection<string, PedigreeAncestor>  $byPath
     */
    private function shown(Pigeon $anchor, string $path, Collection $byPath): bool
    {
        if (! $anchor->hasExternalParent($this->side($path))) {
            return false;
        }

        for ($length = 2; $length < strlen($path); $length++) {
            if (! $byPath->has(substr($path, 0, $length))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The ring key of the bird in front of $path (its offspring in the tree) and the side letter of $path.
     * Behind a loft bird there is nothing to sync: every pedigree links that bird's own record.
     *
     * @return array{0: ?string, 1: string}
     */
    private function offspringOf(Pigeon $anchor, string $path): array
    {
        if (strlen($path) < 2) {
            return [null, substr($path, -1)];
        }

        $offspring = $this->detailsAt($anchor, substr($path, 0, -1));

        return [LoftGraph::normalizeRing($offspring['ring_number'] ?? null), substr($path, -1)];
    }

    /**
     * @param  array{anchor: Pigeon, path: string, row: ?PedigreeAncestor}  $record
     */
    private function write(array $record, array $changes): void
    {
        if ($record['row']) {
            $record['row']->update($changes);

            return;
        }

        $side = $this->side($record['path']);

        $record['anchor']->update(collect($changes)
            ->mapWithKeys(fn (mixed $value, string $field) => ["{$side}_".self::FIELDS[$field] => $value])
            ->all());
    }

    private function side(string $path): string
    {
        return $path[0] === 'S' ? 'sire' : 'dam';
    }

    private static function label(Pigeon $pigeon): string
    {
        return $pigeon->ring_number ?: $pigeon->name ?: $pigeon->personal_number ?: "Pigeon #{$pigeon->id}";
    }

    /**
     * Loft birds whose pedigrees were updated, and those left unchanged because they hold a different bird there.
     *
     * @param  list<Pigeon>  $updated
     * @param  list<Pigeon>  $skipped
     * @return array{updated: list<string>, skipped: list<string>}
     */
    private static function report(array $updated = [], array $skipped = []): array
    {
        $labels = fn (array $pigeons) => array_values(array_unique(array_map(self::label(...), $pigeons)));

        return ['updated' => $labels($updated), 'skipped' => $labels($skipped)];
    }
}
