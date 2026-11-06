<?php

namespace App\Http\Controllers;

use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\PlannedPairing;
use App\Services\Lineage\LineageService;
use App\Services\Lineage\LoftGraph;
use App\Services\Merits\GoldenPairs;
use App\Services\PedigreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Test pairing: before pairing two birds, see how related they are, which ancestors the youngster
 * would be line-bred on, and whether birds already carrying both of them have proven themselves.
 */
class TestPairingController extends Controller
{
    public function __construct(
        private LineageService $lineage,
        private GoldenPairs $goldenPairs,
        private PedigreeService $pedigrees,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $graph = $this->lineage->graph($user->id);

        $sire = $graph->loftBird($request->input('sire'));
        $dam = $graph->loftBird($request->input('dam'));
        $parents = $this->lineage->summaries($graph, array_filter([$sire, $dam]));

        $plans = PlannedPairing::query()
            ->where('user_id', $user->id)
            ->latest()
            ->latest('id')
            ->get();

        $plan = $sire && $dam ? $plans->first(fn (PlannedPairing $plan) => 'p:'.$plan->sire_id === $sire && 'p:'.$plan->dam_id === $dam) : null;
        $paired = $sire && $dam && $sire !== $dam;

        return Inertia::render('test-pairing/Index', [
            'sire' => $sire ? $parents[$sire] : null,
            'dam' => $dam ? $parents[$dam] : null,
            'generations' => LineageService::PEDIGREE_GENERATIONS + 1,
            'analysis' => $paired ? $this->analyse($graph, $sire, $dam, $user->id) : null,
            // The pedigree a youngster of this pair would have, for breeders who read pedigrees rather than numbers
            'sample' => $paired ? $this->pedigrees->sampleChart(
                $user,
                Pigeon::where('user_id', $user->id)->findOrFail($graph->node($sire)['pigeon_id']),
                Pigeon::where('user_id', $user->id)->findOrFail($graph->node($dam)['pigeon_id']),
            ) : null,
            'plan' => $plan ? ['id' => $plan->id, 'notes' => $plan->notes] : null,
            'plans' => $this->plans($graph, $plans, $user->id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyse(LoftGraph $graph, string $sire, string $dam, int $userId): array
    {
        $mating = $graph->mating($sire, $dam, LineageService::PEDIGREE_GENERATIONS);
        $coi = $mating['coefficient'];

        $sireF = $graph->inbreeding($sire, LineageService::PEDIGREE_GENERATIONS + 1);
        $damF = $graph->inbreeding($dam, LineageService::PEDIGREE_GENERATIONS + 1);

        // Ancestors appearing more than once (what the pairing is line-bred on) first, then the biggest shares
        $influential = collect($mating['shares'])
            ->except([$sire, $dam])
            ->filter(fn ($row) => count($row['positions']) > 1 || $row['share'] >= 0.125)
            ->sort(fn ($a, $b) => [count($b['positions']) > 1, $b['share']] <=> [count($a['positions']) > 1, $a['share']])
            ->take(12);

        $common = array_slice($mating['common'], 0, 15, true);

        $summaries = $this->lineage->summaries($graph, [...array_keys($common), ...$influential->keys()]);

        $sireId = $graph->node($sire)['pigeon_id'];
        $damId = $graph->node($dam)['pigeon_id'];

        return [
            'coi' => $coi,
            // Wright's coefficient of relationship between the two birds
            'relationship' => 2 * $coi / sqrt((1 + $sireF) * (1 + $damF)),
            'sire_coi' => $sireF,
            'dam_coi' => $damF,
            'assessment' => self::assess($coi),
            'common_ancestors' => collect($common)->map(fn ($row, $key) => [
                'bird' => $summaries[$key],
                'contribution' => $row['contribution'],
                'lines' => $row['lines'],
            ])->values()->all(),
            'influential_ancestors' => $influential->map(fn ($row, $key) => [
                'bird' => $summaries[$key],
                'share' => $row['share'],
                'positions' => $row['positions'],
            ])->values()->all(),
            'completeness' => $mating['completeness'],
            'shared' => $this->lineage->sharedReport($graph, $sire, $dam),
            'golden_pair' => $this->goldenPairs->forPair($userId, $sireId, $damId),
            'pairings' => Pairing::query()
                ->where('user_id', $userId)
                ->where('sire_id', $sireId)
                ->where('dam_id', $damId)
                ->latest('started_at')
                ->get(['id', 'pair_name', 'status', 'started_at'])
                ->map(fn (Pairing $pairing) => [
                    'id' => $pairing->id,
                    'pair_name' => $pairing->pair_name,
                    'status' => $pairing->status,
                    'started_at' => $pairing->started_at?->format('Y-m-d'),
                ]),
        ];
    }

    /**
     * The breeding plan, each pair with its COI, Golden Pair status and birds used in more than one plan.
     *
     * @param  Collection<int, PlannedPairing>  $plans
     * @return list<array<string, mixed>>
     */
    private function plans(LoftGraph $graph, Collection $plans, int $userId): array
    {
        $plans = $plans->filter(fn (PlannedPairing $plan) => $graph->loftBird($plan->sire_id) && $graph->loftBird($plan->dam_id));

        if ($plans->isEmpty()) {
            return [];
        }

        $summaries = $this->lineage->summaries($graph, $plans->flatMap(fn (PlannedPairing $plan) => [
            LoftGraph::pigeonKey($plan->sire_id),
            LoftGraph::pigeonKey($plan->dam_id),
        ]));
        $golden = $this->goldenPairs->forPairs($userId, $plans->map(fn (PlannedPairing $plan) => [$plan->sire_id, $plan->dam_id]));
        $uses = $plans->flatMap(fn (PlannedPairing $plan) => [$plan->sire_id, $plan->dam_id])->countBy();

        return $plans->map(function (PlannedPairing $plan) use ($graph, $summaries, $golden, $uses) {
            $sire = LoftGraph::pigeonKey($plan->sire_id);
            $dam = LoftGraph::pigeonKey($plan->dam_id);
            $coi = $graph->mating($sire, $dam, LineageService::PEDIGREE_GENERATIONS)['coefficient'];
            $pair = $golden[GoldenPairs::key($plan->sire_id, $plan->dam_id)] ?? null;

            return [
                'id' => $plan->id,
                'notes' => $plan->notes,
                'created_at' => $plan->created_at?->toDateString(),
                'sire' => $summaries[$sire],
                'dam' => $summaries[$dam],
                'coi' => $coi,
                'assessment' => self::assess($coi),
                'golden' => $pair['golden'] ?? false,
                'winner_count' => $pair['winner_count'] ?? 0,
                // How many plans each bird is in: a cock or hen planned with two partners needs a decision
                'sire_plans' => $uses[$plan->sire_id] ?? 1,
                'dam_plans' => $uses[$plan->dam_id] ?? 1,
            ];
        })->values()->all();
    }

    /**
     * Where an inbreeding coefficient sits, with the familiar mating that produces the same value.
     *
     * @return array{level: string, label: string, equivalent: string}
     */
    public static function assess(float $coi): array
    {
        // Small tolerance so 0.0625 computed through floats still lands in its band
        $coi = round($coi, 6);

        return match (true) {
            $coi <= 0 => ['level' => 'outcross', 'label' => 'Outcross', 'equivalent' => 'No shared ancestors in the recorded pedigrees'],
            $coi < 0.03125 => ['level' => 'light', 'label' => 'Light line breeding', 'equivalent' => 'About as related as second cousins or more distant'],
            $coi < 0.125 => ['level' => 'linebreeding', 'label' => 'Line breeding', 'equivalent' => $coi >= 0.0625 ? 'About first cousins (6.25%) or closer' : 'Between second and first cousins'],
            $coi < 0.25 => ['level' => 'close', 'label' => 'Close line breeding', 'equivalent' => 'About half-siblings, grandparent × grandchild or uncle × niece (12.5%)'],
            default => ['level' => 'inbreeding', 'label' => 'Inbreeding', 'equivalent' => 'Full siblings or parent × offspring (25%) or closer'],
        };
    }
}
