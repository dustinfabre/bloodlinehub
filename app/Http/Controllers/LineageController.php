<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Services\Lineage\LineageService;
use App\Services\Lineage\LoftGraph;
use App\Services\Merits\GoldenPairs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Family search: a bird's descendants (children, grandchildren, greats...), its siblings and mates,
 * or every bird that descends from two given birds (the offspring of a pair and their descendants).
 */
class LineageController extends Controller
{
    public function __construct(private LineageService $lineage) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $mode = $request->input('mode') === 'pair' ? 'pair' : 'bird';
        $graph = $this->lineage->graph($user->id);

        $props = [
            'mode' => $mode,
            'generations' => LineageService::DESCENDANT_GENERATIONS,
            'bird' => null,
            'family' => null,
            'a' => null,
            'b' => null,
            'shared' => null,
        ];

        if ($mode === 'bird' && ($key = $graph->loftBird($request->input('pigeon')))) {
            [$props['bird'], $props['family']] = $this->family($graph, $key);
        }

        if ($mode === 'pair') {
            $a = $graph->loftBird($request->input('a'));
            $b = $graph->loftBird($request->input('b'));
            $summaries = $this->lineage->summaries($graph, array_filter([$a, $b]));
            $props['a'] = $a ? $summaries[$a] : null;
            $props['b'] = $b ? $summaries[$b] : null;

            if ($a && $b && $a !== $b) {
                $props['shared'] = $this->lineage->sharedReport($graph, $a, $b);
            }
        }

        return Inertia::render('lineage/Index', $props);
    }

    /**
     * Bird picker search (JSON). Optional gender narrows to that gender plus birds with no gender recorded;
     * in_loft leaves out deceased, flyaway and missing birds (e.g. when choosing birds to pair).
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $gender = in_array($request->input('gender'), ['male', 'female'], true) ? $request->input('gender') : null;
        $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $pigeons = Pigeon::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('in_loft'), fn ($q) => $q->inLoft())
            ->when($gender, fn ($q) => $q->where(fn ($g) => $g->where('gender', $gender)->orWhereNull('gender')))
            ->when($term !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('ring_number', $likeOp, "%{$term}%")
                ->orWhere('name', $likeOp, "%{$term}%")
                ->orWhere('personal_number', $likeOp, "%{$term}%")
                ->orWhere('bloodline', $likeOp, "%{$term}%")
            ))
            ->orderByRaw('ring_number IS NULL')
            ->orderBy('ring_number')
            ->limit(20)
            ->with(Pigeon::SEARCH_RELATIONS)
            ->get([...Pigeon::SEARCH_COLUMNS, 'status']);

        // The standard pigeon search format (see CLAUDE.md); only the user's own birds, so notes are fine
        return response()->json(['pigeons' => $pigeons->map(fn (Pigeon $pigeon) => [
            ...$pigeon->searchSummary(),
            'status' => $pigeon->status,
        ])]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function family(LoftGraph $graph, string $key): array
    {
        $descendants = $graph->descendants($key, LineageService::DESCENDANT_GENERATIONS);
        [$sire, $dam] = $graph->parentsOf($key);

        // Siblings share at least one recorded parent; external parents are matched by ring number
        $sireChildren = $sire ? $graph->childrenOf($sire) : [];
        $damChildren = $dam ? $graph->childrenOf($dam) : [];
        $full = array_values(array_diff(array_intersect($sireChildren, $damChildren), [$key]));
        $halfSire = array_values(array_diff($sireChildren, $full, [$key]));
        $halfDam = array_values(array_diff($damChildren, $full, [$key]));

        // Mates: the other parent of each child
        $mates = [];
        foreach ($graph->childrenOf($key) as $child) {
            [$childSire, $childDam] = $graph->parentsOf($child);
            $mate = $childSire === $key ? $childDam : $childSire;
            $mates[$mate ?? 'unknown'][] = $child;
        }

        $summaries = $this->lineage->summaries($graph, [
            $key, ...array_filter([$sire, $dam]),
            ...array_keys($descendants),
            ...array_merge(...array_column($descendants, 'via')),
            ...$full, ...$halfSire, ...$halfDam,
            ...array_filter(array_keys($mates), fn ($mate) => $mate !== 'unknown'),
        ]);

        $generations = [];
        foreach ($descendants as $descendantKey => $line) {
            [$childSire, $childDam] = $graph->parentsOf($descendantKey);
            $otherParent = $line['generation'] === 1 ? ($childSire === $key ? $childDam : $childSire) : null;

            $generations[$line['generation']][] = [
                'bird' => $summaries[$descendantKey],
                'via' => array_map(fn ($via) => $this->lineage->mini($summaries[$via]), $line['via']),
                'other_parent' => $otherParent ? $this->lineage->mini($summaries[$otherParent]) : null,
                'share' => $graph->geneShare($key, $descendantKey),
            ];
        }
        ksort($generations);

        return [
            $summaries[$key],
            [
                'sire' => $sire ? $summaries[$sire] : null,
                'dam' => $dam ? $summaries[$dam] : null,
                'descendants' => collect($generations)->map(fn ($birds, $generation) => [
                    'generation' => $generation,
                    'label' => LineageService::descendantLabel($generation),
                    'birds' => $this->lineage->rank($birds, fn ($row) => $row['bird']),
                ])->values()->all(),
                'siblings' => [
                    'full' => $this->lineage->rank(array_map(fn ($k) => $summaries[$k], $full)),
                    'sire_side' => $this->lineage->rank(array_map(fn ($k) => $summaries[$k], $halfSire)),
                    'dam_side' => $this->lineage->rank(array_map(fn ($k) => $summaries[$k], $halfDam)),
                ],
                'mates' => collect($mates)
                    ->map(function ($children, $mate) use ($summaries) {
                        $winners = GoldenPairs::countWinners(array_map(fn ($child) => $summaries[$child], $children));

                        return [
                            'bird' => $mate === 'unknown' ? null : $summaries[$mate],
                            'offspring' => count($children),
                            'proven' => count(array_filter($children, fn ($child) => $this->lineage->isProven($summaries[$child]))),
                            'winners' => $winners,
                            'golden' => $mate !== 'unknown' && $winners >= GoldenPairs::MIN_WINNERS,
                        ];
                    })
                    ->sortByDesc('offspring')
                    ->values()
                    ->all(),
            ],
        ];
    }
}
