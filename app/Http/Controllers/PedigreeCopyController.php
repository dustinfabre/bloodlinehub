<?php

namespace App\Http\Controllers;

use App\Models\PedigreeAncestor;
use App\Models\Pigeon;
use App\Services\Lineage\LoftGraph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Copy a whole pedigree side (the sire or dam and everything recorded behind it) from another loft bird,
 * e.g. for full or half siblings bought from the same breeder.
 *
 * A parent that is a loft bird is linked rather than copied, so its own pedigree keeps coming along. An external
 * parent is copied with its ring numbers unchanged, which is how Lineage and Test Pairing recognise the same
 * ancestor in both pedigrees (they match external birds by ring number).
 */
class PedigreeCopyController extends Controller
{
    private const SIDES = ['sire' => 'S', 'dam' => 'D'];

    // External parent details kept on the pigeon itself, per side ("sire_name", "dam_notes", ...)
    private const PARENT_COLUMNS = ['name', 'ring_number', 'color', 'notes', 'merits'];

    private const ANCESTOR_COLUMNS = ['path', 'ring_number', 'name', 'color', 'remarks', 'merits'];

    private const SOURCE_LIMIT = 20;

    /**
     * Birds to copy from (JSON): birds that already share a parent with this one first, then the most recently added.
     */
    public function sources(Request $request, Pigeon $pigeon): JsonResponse
    {
        $this->ensureOwner($request, $pigeon);

        $term = trim((string) $request->input('q', ''));
        $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $pigeon->loadMissing(['sire:id,ring_number', 'dam:id,ring_number']);

        $candidates = Pigeon::query()
            ->where('user_id', $pigeon->user_id)
            ->whereKeyNot($pigeon->id)
            // Only birds with something recorded on at least one side
            ->where(function ($query) {
                foreach (array_keys(self::SIDES) as $side) {
                    foreach (['id', 'name', 'ring_number', 'color', 'notes'] as $column) {
                        $query->orWhereNotNull("{$side}_{$column}");
                    }
                }
            })
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('ring_number', $likeOp, "%{$term}%")
                ->orWhere('name', $likeOp, "%{$term}%")
                ->orWhere('personal_number', $likeOp, "%{$term}%")
            ))
            ->with(['sire:id,name,ring_number', 'dam:id,name,ring_number'])
            ->get()
            ->map(fn (Pigeon $candidate) => [
                'pigeon' => $candidate,
                'shared' => array_values(array_filter(
                    array_keys(self::SIDES),
                    fn (string $side) => $this->sameParent($pigeon, $candidate, $side),
                )),
            ])
            ->sort(fn (array $a, array $b) => [count($b['shared']), $b['pigeon']->id] <=> [count($a['shared']), $a['pigeon']->id])
            ->take(self::SOURCE_LIMIT)
            ->values();

        $ancestorCounts = PedigreeAncestor::query()
            ->whereIn('pigeon_id', $candidates->pluck('pigeon.id'))
            ->selectRaw('pigeon_id, substr(path, 1, 1) as side, count(*) as total')
            ->groupByRaw('pigeon_id, substr(path, 1, 1)')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->pigeon_id}:{$row->side}" => (int) $row->total]);

        return response()->json([
            'pigeons' => $candidates->map(fn (array $row) => [
                'id' => $row['pigeon']->id,
                'name' => $row['pigeon']->name,
                'ring_number' => $row['pigeon']->ring_number,
                'personal_number' => $row['pigeon']->personal_number,
                'gender' => $row['pigeon']->gender,
                // Sides on which this bird already has the same parent as the one being edited
                'shared' => $row['shared'],
                ...collect(self::SIDES)->map(fn (string $letter, string $side) => $this->sideSummary(
                    $row['pigeon'],
                    $side,
                    $ancestorCounts->get("{$row['pigeon']->id}:{$letter}", 0),
                ))->all(),
            ]),
        ]);
    }

    public function store(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);

        $validated = $request->validate([
            'source_id' => [
                'required',
                'integer',
                Rule::exists('pigeons', 'id')->where('user_id', $pigeon->user_id),
                Rule::notIn([$pigeon->id]),
            ],
            'sides' => ['required', 'array', 'min:1'],
            'sides.*' => ['distinct', Rule::in(array_keys(self::SIDES))],
        ], [
            'source_id.not_in' => 'Choose a different pigeon to copy from.',
            'sides.required' => 'Choose the sire side, the dam side or both.',
        ]);

        $source = Pigeon::with(['sire', 'dam', 'pedigreeAncestors'])->findOrFail($validated['source_id']);
        $sides = $validated['sides'];

        // Check every side first so a problem with one never leaves the other half copied
        foreach ($sides as $side) {
            $this->ensureCopyable($pigeon, $source, $side);
        }

        DB::transaction(function () use ($pigeon, $source, $sides) {
            foreach ($sides as $side) {
                $this->copySide($pigeon, $source, $side);
            }
        });

        $what = count($sides) === 2 ? 'both sides' : "the {$sides[0]} side";
        $from = $source->ring_number ?: $source->name ?: 'the other pigeon';

        return back()->with('success', "Copied {$what} from {$from}.");
    }

    private function ensureCopyable(Pigeon $target, Pigeon $source, string $side): void
    {
        $sourceParentId = $source->{"{$side}_id"};
        $targetParentId = $target->{"{$side}_id"};

        $message = match (true) {
            ! $sourceParentId && ! $source->hasExternalParent($side) => "That pigeon has no {$side} recorded.",
            $sourceParentId === $target->id => "A pigeon can't be its own {$side}.",
            $targetParentId && $targetParentId !== $sourceParentId => "This pigeon's {$side} is a bird in your loft. Unlink it on the pigeon's edit page before copying.",
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['sides' => $message]);
        }
    }

    private function copySide(Pigeon $target, Pigeon $source, string $side): void
    {
        $letter = self::SIDES[$side];
        $parent = $source->{"{$side}_id"} ? $source->{$side} : null;

        // Already linked to the same loft bird: nothing to change
        if ($parent && $target->{"{$side}_id"} === $parent->id) {
            return;
        }

        // Anything recorded behind the parent being replaced belonged to a different bird
        $target->pedigreeAncestors()->where('path', 'like', $letter.'%')->delete();

        if ($parent) {
            // Link the same loft bird; like the pigeon form, keep a copy of its name, ring and color
            $target->update([
                "{$side}_id" => $parent->id,
                ...collect(self::PARENT_COLUMNS)->mapWithKeys(fn (string $column) => [
                    "{$side}_{$column}" => in_array($column, ['name', 'ring_number', 'color'], true) ? $parent->{$column} : null,
                ])->all(),
            ]);

            return;
        }

        $target->update([
            "{$side}_id" => null,
            ...collect(self::PARENT_COLUMNS)->mapWithKeys(fn (string $column) => [
                "{$side}_{$column}" => $source->{"{$side}_{$column}"},
            ])->all(),
        ]);

        $source->pedigreeAncestors
            ->filter(fn (PedigreeAncestor $ancestor) => str_starts_with($ancestor->path, $letter))
            ->each(fn (PedigreeAncestor $ancestor) => $target->pedigreeAncestors()->create($ancestor->only(self::ANCESTOR_COLUMNS)));
    }

    /**
     * Whether two birds have the same parent on $side: the same linked loft bird, or the same ring number.
     */
    private function sameParent(Pigeon $a, Pigeon $b, string $side): bool
    {
        if ($a->{"{$side}_id"} && $a->{"{$side}_id"} === $b->{"{$side}_id"}) {
            return true;
        }

        $ring = LoftGraph::normalizeRing($this->parentRing($a, $side));

        return $ring !== null && $ring === LoftGraph::normalizeRing($this->parentRing($b, $side));
    }

    private function parentRing(Pigeon $pigeon, string $side): ?string
    {
        return $pigeon->{"{$side}_id"} ? $pigeon->{$side}?->ring_number : $pigeon->{"{$side}_ring_number"};
    }

    /**
     * @return array{state: 'linked'|'external'|'empty', pigeon_id: ?int, ring_number: ?string, name: ?string, ancestors: ?int}
     */
    private function sideSummary(Pigeon $pigeon, string $side, int $ancestors): array
    {
        if ($pigeon->{"{$side}_id"}) {
            $parent = $pigeon->{$side};

            return [
                'state' => 'linked',
                'pigeon_id' => $pigeon->{"{$side}_id"},
                'ring_number' => $parent?->ring_number,
                'name' => $parent?->name,
                // A loft bird brings its own pedigree; there is nothing recorded on this pigeon to count
                'ancestors' => null,
            ];
        }

        $external = $pigeon->hasExternalParent($side);

        return [
            'state' => $external ? 'external' : 'empty',
            'pigeon_id' => null,
            'ring_number' => $external ? $pigeon->{"{$side}_ring_number"} : null,
            'name' => $external ? $pigeon->{"{$side}_name"} : null,
            'ancestors' => $external ? $ancestors : 0,
        ];
    }

    private function ensureOwner(Request $request, Pigeon $pigeon): void
    {
        if ($pigeon->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}
