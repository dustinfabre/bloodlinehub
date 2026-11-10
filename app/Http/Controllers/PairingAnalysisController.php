<?php

namespace App\Http\Controllers;

use App\Models\Pairing;
use App\Models\Pigeon;
use App\Models\PlannedPairing;
use App\Services\Lineage\LineageService;
use App\Services\Lineage\LoftGraph;
use App\Services\Lineage\PairingAnalysis;
use App\Services\Merits\GoldenPairs;
use App\Services\PedigreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pairing Analysis: before pairing two birds, see how related they are, which ancestors the youngster
 * would be line-bred on, and whether birds already carrying both of them have proven themselves.
 */
class PairingAnalysisController extends Controller
{
    public function __construct(
        private LineageService $lineage,
        private PairingAnalysis $analysis,
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

        return Inertia::render('pairing-analysis/Index', [
            'sire' => $sire ? $parents[$sire] : null,
            'dam' => $dam ? $parents[$dam] : null,
            'generations' => LineageService::PEDIGREE_GENERATIONS + 1,
            'analysis' => $paired ? [
                ...$this->analysis->analyse($graph, $sire, $dam, $user->id),
                'pairings' => $this->pairings($user->id, $graph->node($sire)['pigeon_id'], $graph->node($dam)['pigeon_id']),
            ] : null,
            // The pedigree a youngster of this pair would have, for breeders who read pedigrees rather than numbers
            'sample' => $paired ? $this->pedigrees->sampleChart(
                $user,
                Pigeon::where('user_id', $user->id)->findOrFail($graph->node($sire)['pigeon_id']),
                Pigeon::where('user_id', $user->id)->findOrFail($graph->node($dam)['pigeon_id']),
                $this->analysis->sheet($graph, $sire, $dam),
            ) : null,
            'plan' => $plan ? ['id' => $plan->id, 'notes' => $plan->notes] : null,
            'plans' => $this->plans($graph, $plans, $user->id),
        ]);
    }

    /**
     * Earlier pairings of these two birds, newest first.
     *
     * @return list<array{id: int, pair_name: ?string, status: string, started_at: ?string}>
     */
    private function pairings(int $userId, int $sireId, int $damId): array
    {
        return Pairing::query()
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
            ])
            ->all();
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
                'assessment' => PairingAnalysis::assess($coi),
                'golden' => $pair['golden'] ?? false,
                'winner_count' => $pair['winner_count'] ?? 0,
                // How many plans each bird is in: a cock or hen planned with two partners needs a decision
                'sire_plans' => $uses[$plan->sire_id] ?? 1,
                'dam_plans' => $uses[$plan->dam_id] ?? 1,
            ];
        })->values()->all();
    }
}
