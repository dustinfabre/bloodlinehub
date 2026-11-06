<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\Lineage\LineageService;
use App\Services\Lineage\LoftGraph;
use App\Services\PedigreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Copy part of a pedigree from another loft bird: a bird up to great-grandparent level and everything recorded behind
 * it, e.g. the whole sire side from a full sibling, or a great-grandsire's line from a bird related only through him.
 *
 * Each copy takes the bird at position "from" in the other pigeon's pedigree (e.g. "SSS", its sire's sire's sire) and
 * puts it at position "to" in this pigeon's pedigree. Outside ancestors are copied with their ring numbers unchanged,
 * which is how Lineage and Test Pairing recognise the same bird in both pedigrees. A loft bird copied into the place of
 * a loft bird's parent is linked instead, so its own pedigree keeps coming along. Anything deeper is stored like any
 * outside ancestor: on the nearest loft bird in front of it (see PedigreeAncestorController), so if that is another
 * loft bird, its offspring inherit the copy too.
 */
class PedigreeCopyController extends Controller
{
    private const SIDES = ['sire' => 'S', 'dam' => 'D'];

    // Deepest position copied from or into: great-grandparents
    private const MAX_COPY_PATH = 3;

    // Deepest path stored from a loft bird (great-great-grandparents); ancestors further back are left out
    private const MAX_PATH = 4;

    // An outside bird's details => the column suffix for a parent stored on the pigeon itself (sire_name, ...)
    private const PARENT_COLUMNS = [
        'name' => 'name',
        'ring_number' => 'ring_number',
        'color' => 'color',
        'bloodline' => 'bloodline',
        'remarks' => 'notes',
        'merits' => 'merits',
    ];

    private const SOURCE_LIMIT = 20;

    /** @var array<int, array> Pedigree trees built for the current copy, by pigeon id (reset per request: the router keeps controller instances) */
    private array $trees = [];

    public function __construct(
        private PedigreeService $pedigrees,
        private LineageService $lineage,
    ) {}

    /**
     * Birds to copy from (JSON), in the standard pigeon search format. Birds related to this one come first, with
     * where they share an ancestor: "related" pairs the position in their pedigree ("from") with the position of the
     * same bird in this one ("to"), matched by loft bird or ring number.
     */
    public function sources(Request $request, Pigeon $pigeon): JsonResponse
    {
        $this->ensureOwner($request, $pigeon);

        $term = trim((string) $request->input('q', ''));
        $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $graph = $this->lineage->graph($pigeon->user_id);
        $here = $this->positions($graph, $pigeon);

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
            ->with(Pigeon::SEARCH_RELATIONS)
            ->get(Pigeon::SEARCH_COLUMNS)
            ->map(function (Pigeon $candidate) use ($graph, $here) {
                $related = $this->related($this->positions($graph, $candidate), $here);

                return [
                    'pigeon' => $candidate,
                    'related' => $related,
                    // The closer the shared ancestor, the closer the relation: a shared parent counts most
                    'closeness' => array_sum(array_map(fn (array $pair) => 2 ** -strlen($pair['to']), $related)),
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['closeness'], $b['pigeon']->id] <=> [$a['closeness'], $a['pigeon']->id])
            ->take(self::SOURCE_LIMIT)
            ->values();

        return response()->json([
            'pigeons' => $candidates->map(fn (array $row) => [
                ...$row['pigeon']->searchSummary(),
                'related' => $row['related'],
            ]),
        ]);
    }

    /**
     * The pedigree of a bird to copy from (JSON), to choose which of its ancestors to copy.
     */
    public function source(Request $request, Pigeon $pigeon, Pigeon $source): JsonResponse
    {
        $this->ensureOwner($request, $pigeon);

        if ($source->user_id !== $pigeon->user_id) {
            abort(404);
        }

        return response()->json(['tree' => $this->pedigrees->tree($source)]);
    }

    public function store(Request $request, Pigeon $pigeon): RedirectResponse
    {
        $this->ensureOwner($request, $pigeon);
        $this->trees = [];

        // "sides" is shorthand for copying whole sides into the same sides: ["sire"] means S into S
        $bySide = $request->has('sides');
        $errorKey = $bySide ? 'sides' : 'copies';

        $validated = $request->validate([
            'source_id' => [
                'required',
                'integer',
                Rule::exists('pigeons', 'id')->where('user_id', $pigeon->user_id),
                Rule::notIn([$pigeon->id]),
            ],
            'sides' => ['required_without:copies', 'array', 'min:1'],
            'sides.*' => ['distinct', Rule::in(array_keys(self::SIDES))],
            'copies' => ['required_without:sides', 'array', 'min:1', 'max:14'],
            'copies.*.from' => ['required', 'string', 'regex:/^[SD]{1,'.self::MAX_COPY_PATH.'}$/'],
            'copies.*.to' => ['required', 'string', 'distinct', 'regex:/^[SD]{1,'.self::MAX_COPY_PATH.'}$/'],
        ], [
            'source_id.not_in' => 'Choose a different pigeon to copy from.',
            'sides.required_without' => 'Choose the sire side, the dam side or both.',
            'copies.required_without' => 'Choose what to copy.',
            'copies.*.to.distinct' => 'Choose a different place for each bird.',
        ]);

        $source = Pigeon::findOrFail($validated['source_id']);
        $copies = $bySide
            ? array_map(fn (string $side) => ['from' => self::SIDES[$side], 'to' => self::SIDES[$side]], $validated['sides'])
            : array_map(fn (array $copy) => ['from' => $copy['from'], 'to' => $copy['to']], $validated['copies']);

        // Check every copy first so a problem with one never leaves the others half done
        $fail = fn (string $message) => throw ValidationException::withMessages([$errorKey => $message]);
        $this->ensureNoOverlap($copies, $fail);
        $plans = array_values(array_filter(array_map(
            fn (array $copy) => $this->plan($pigeon, $source, $copy['from'], $copy['to'], $fail),
            $copies,
        )));

        DB::transaction(function () use ($plans) {
            foreach ($plans as $plan) {
                $this->write($plan);
            }
        });

        $from = $source->ring_number ?: $source->name ?: 'the other pigeon';
        $what = match (true) {
            $bySide && count($copies) === 2 => 'both sides',
            $bySide => 'the '.array_search($copies[0]['to'], self::SIDES, true).' side',
            count($copies) === 1 => 'the '.self::relation($copies[0]['to']),
            default => count($copies).' parts of the pedigree',
        };

        return back()->with('success', "Copied {$what} from {$from}.");
    }

    /**
     * Work out one copy: where the bird goes, and what to write there. Null when there is nothing to change.
     *
     * @param  callable(string): never  $fail
     * @return array{anchor: Pigeon, path: string, bird: array, link: bool}|null
     */
    private function plan(Pigeon $target, Pigeon $source, string $from, string $to, callable $fail): ?array
    {
        if ($from[-1] !== $to[-1]) {
            $fail('Copy a sire into the place of a sire, and a dam into the place of a dam.');
        }

        $bird = self::at($this->tree($source), $from)
            ?? $fail('That pigeon has no '.self::relation($from).' recorded.');

        // An ancestor needs the bird in front of it (its offspring in the tree) to be known first
        $offspring = self::at($this->tree($target), substr($to, 0, -1))
            ?? $fail('Add the '.self::relation(substr($to, 0, -1)).' before copying behind it.');
        $current = self::at($this->tree($target), $to);

        // Stored on the nearest loft bird in front, by path from it
        [$anchorId, $path] = $offspring['external']
            ? [$offspring['anchor_id'], $offspring['path'].$to[-1]]
            : [$offspring['id'], $to[-1]];
        $anchor = $anchorId === $target->id ? $target : Pigeon::findOrFail($anchorId);
        $side = $path[0] === 'S' ? 'sire' : 'dam';

        if ($current && ! $current['external']) {
            if (! $bird['external'] && $bird['id'] === $current['id']) {
                return null;
            }

            $fail(strlen($to) === 1
                ? "This pigeon's {$side} is a bird in your loft. Unlink it on the pigeon's edit page before copying."
                : 'The '.self::relation($to).' is a bird in your loft. Change it on that bird\'s own page instead.');
        }

        // Already this very record (e.g. copying from a bird that shares the same loft parent)
        if ($current && $bird['external'] && $bird['anchor_id'] === $current['anchor_id'] && $bird['path'] === $current['path']) {
            return null;
        }

        $link = ! $bird['external'] && strlen($path) === 1;

        if ($link && $bird['id'] === $anchor->id) {
            $fail("A pigeon can't be its own {$side}.");
        }

        // Whatever comes along must not lead back to this pigeon or the bird it is stored on, or the pedigree loops
        $whole = $bird['external']
            ? self::at($this->tree(Pigeon::findOrFail($bird['anchor_id'])), $bird['path'])
            : $this->tree(Pigeon::findOrFail($bird['id']));

        if (array_intersect([$target->id, $anchor->id], self::loftIds($whole))) {
            $fail('That part of the pedigree includes this pigeon itself, so it can\'t be copied here.');
        }

        return ['anchor' => $anchor, 'path' => $path, 'bird' => $whole, 'link' => $link];
    }

    /**
     * @param  array{anchor: Pigeon, path: string, bird: array, link: bool}  $plan
     */
    private function write(array $plan): void
    {
        ['anchor' => $anchor, 'path' => $path, 'bird' => $bird, 'link' => $link] = $plan;

        // Anything recorded here before belonged to the bird being replaced
        $anchor->pedigreeAncestors()->where('path', 'like', $path.'%')->delete();

        if (strlen($path) === 1) {
            $side = $path === 'S' ? 'sire' : 'dam';

            if ($link) {
                // Link the same loft bird; like the pigeon form, keep a copy of its name, ring and color
                $anchor->update([
                    "{$side}_id" => $bird['id'],
                    ...$this->parentColumns($side, collect($bird)->only(['name', 'ring_number', 'color'])->all()),
                ]);

                return;
            }

            $anchor->update(["{$side}_id" => null, ...$this->parentColumns($side, self::details($bird))]);
        } else {
            $anchor->pedigreeAncestors()->create(['path' => $path, ...self::details($bird)]);
        }

        foreach (self::behind($bird) as $suffix => $ancestor) {
            if (strlen($path.$suffix) <= self::MAX_PATH) {
                $anchor->pedigreeAncestors()->create(['path' => $path.$suffix, ...self::details($ancestor)]);
            }
        }
    }

    /**
     * Two copies can't go into the same branch: one would overwrite the other.
     *
     * @param  list<array{from: string, to: string}>  $copies
     * @param  callable(string): never  $fail
     */
    private function ensureNoOverlap(array $copies, callable $fail): void
    {
        foreach ($copies as $a) {
            foreach ($copies as $b) {
                if ($a !== $b && str_starts_with($b['to'], $a['to'])) {
                    $fail('The '.self::relation($b['to']).' is already copied along with the '.self::relation($a['to']).'. Choose one of them.');
                }
            }
        }
    }

    /**
     * Ancestors up to great-grandparents, by position: path => graph node key.
     *
     * @return array<string, string>
     */
    private function positions(LoftGraph $graph, Pigeon $pigeon): array
    {
        $positions = [];
        $frontier = ['' => LoftGraph::pigeonKey($pigeon->id)];

        for ($depth = 1; $depth <= self::MAX_COPY_PATH && $frontier; $depth++) {
            $next = [];

            foreach ($frontier as $path => $key) {
                [$sire, $dam] = $graph->parentsOf($key);
                $next += array_filter([$path.'S' => $sire, $path.'D' => $dam]);
            }

            $positions += $next;
            $frontier = $next;
        }

        return $positions;
    }

    /**
     * Where two pedigrees hold the same bird, leaving out pairs that follow from a closer shared bird
     * (if the sire is shared, so are his parents).
     *
     * @param  array<string, string>  $there  positions in the other bird's pedigree
     * @param  array<string, string>  $here  positions in this pigeon's pedigree
     * @return list<array{from: string, to: string}>
     */
    private function related(array $there, array $here): array
    {
        $pairs = [];

        foreach ($there as $from => $key) {
            foreach (array_keys($here, $key, true) as $to) {
                $pairs[] = ['from' => (string) $from, 'to' => (string) $to];
            }
        }

        usort($pairs, fn (array $a, array $b) => [strlen($a['to']), strlen($a['from'])] <=> [strlen($b['to']), strlen($b['from'])]);

        $kept = [];

        foreach ($pairs as $pair) {
            foreach ($kept as $closer) {
                if (str_starts_with($pair['from'], $closer['from'])
                    && str_starts_with($pair['to'], $closer['to'])
                    && substr($pair['from'], strlen($closer['from'])) === substr($pair['to'], strlen($closer['to']))) {
                    continue 2;
                }
            }

            $kept[] = $pair;
        }

        return $kept;
    }

    private function tree(Pigeon $pigeon): array
    {
        return $this->trees[$pigeon->id] ??= $this->pedigrees->tree($pigeon);
    }

    /**
     * The bird at a position of a pedigree tree ("" for the bird itself).
     */
    private static function at(?array $node, string $path): ?array
    {
        foreach ($path === '' ? [] : str_split($path) as $letter) {
            $node = $node[$letter === 'S' ? 'sire' : 'dam'] ?? null;
        }

        return $node;
    }

    /**
     * Every ancestor recorded behind a bird: path from it => node.
     *
     * @return array<string, array>
     */
    private static function behind(array $node, string $prefix = ''): array
    {
        $found = [];

        foreach (self::SIDES as $side => $letter) {
            if ($parent = $node[$side] ?? null) {
                $found[$prefix.$letter] = $parent;
                $found += self::behind($parent, $prefix.$letter);
            }
        }

        return $found;
    }

    /**
     * @return list<int>
     */
    private static function loftIds(?array $node): array
    {
        if (! $node) {
            return [];
        }

        return [
            ...($node['external'] ? [] : [$node['id']]),
            ...self::loftIds($node['sire']),
            ...self::loftIds($node['dam']),
        ];
    }

    /**
     * What is stored for an outside ancestor, from a pedigree tree node (an outside bird or a loft bird).
     */
    private static function details(array $node): array
    {
        return [
            'ring_number' => $node['ring_number'],
            'name' => $node['name'],
            'color' => $node['color'],
            'bloodline' => $node['bloodline'],
            'remarks' => $node['remarks'],
            'merits' => PigeonMerit::cleanList(array_map(
                fn (array $merit) => collect($merit)->only(['type', 'level', 'year', 'detail'])->all(),
                $node['merits'] ?? [],
            )),
        ];
    }

    /**
     * Columns for a parent stored on the pigeon itself; anything not given is cleared.
     */
    private function parentColumns(string $side, array $details): array
    {
        return collect(self::PARENT_COLUMNS)
            ->mapWithKeys(fn (string $column, string $field) => ["{$side}_{$column}" => $details[$field] ?? null])
            ->all();
    }

    /**
     * e.g. "SDS" => "sire's dam's sire"
     */
    private static function relation(string $path): string
    {
        return $path === '' ? 'pigeon' : implode("'s ", array_map(fn (string $letter) => $letter === 'S' ? 'sire' : 'dam', str_split($path)));
    }

    private function ensureOwner(Request $request, Pigeon $pigeon): void
    {
        if ($pigeon->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}
