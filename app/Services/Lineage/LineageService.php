<?php

namespace App\Services\Lineage;

use App\Enums\MeritType;
use App\Models\PedigreeAncestor;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use Illuminate\Support\Facades\DB;

class LineageService
{
    /**
     * How many generations down we look for descendants (children = 1).
     */
    public const DESCENDANT_GENERATIONS = 6;

    /**
     * Generations above the parents used for inbreeding / relationship maths (6 above a youngster).
     */
    public const PEDIGREE_GENERATIONS = 5;

    public function graph(int $userId): LoftGraph
    {
        $pigeons = Pigeon::query()
            ->where('user_id', $userId)
            ->get([
                'id', 'name', 'ring_number', 'gender', 'color', 'sire_id', 'dam_id',
                'sire_name', 'sire_ring_number', 'sire_color', 'sire_merits', 'dam_name', 'dam_ring_number', 'dam_color', 'dam_merits',
            ]);

        $ancestors = PedigreeAncestor::query()
            ->whereIn('pigeon_id', Pigeon::where('user_id', $userId)->select('id'))
            ->get(['pigeon_id', 'path', 'ring_number', 'name', 'color', 'merits'])
            ->groupBy('pigeon_id')
            ->map(fn ($rows) => $rows->keyBy('path'));

        return LoftGraph::build($pigeons, $ancestors);
    }

    /**
     * Display data for graph nodes. Loft birds get their full record, merits and race record;
     * external ancestors only have what the pedigree recorded.
     *
     * @param  iterable<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    public function summaries(LoftGraph $graph, iterable $keys): array
    {
        $keys = collect($keys)->unique()->filter(fn ($key) => $graph->has($key))->values();

        $ids = $keys->map(fn ($key) => $graph->node($key)['pigeon_id'])->filter()->values()->all();

        $pigeons = Pigeon::query()
            ->whereIn('id', $ids)
            ->with(['bloodlines', 'merits'])
            ->get()
            ->keyBy('id');

        $results = $this->raceRecords($ids);

        return $keys->mapWithKeys(function (string $key) use ($graph, $pigeons, $results) {
            $node = $graph->node($key);
            $pigeon = $node['pigeon_id'] ? $pigeons->get($node['pigeon_id']) : null;

            if (! $pigeon) {
                $merits = PigeonMerit::summarizeList($node['merits'] ?? []);

                return [$key => [
                    'key' => $key,
                    'id' => null,
                    'external' => true,
                    'name' => $node['name'],
                    'ring_number' => $node['ring_number'],
                    'personal_number' => null,
                    'gender' => $node['gender'],
                    'color' => $node['color'],
                    'status' => null,
                    'hatch_date' => null,
                    'photo_url' => null,
                    'bloodline' => null,
                    'merits' => $merits,
                    'merit_score' => collect($merits)->sum(fn ($merit) => MeritType::from($merit['type'])->weight()),
                    'results' => null,
                ]];
            }

            $merits = $pigeon->merits->map->summary()->values()->all();

            return [$key => [
                'key' => $key,
                'id' => $pigeon->id,
                'external' => false,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'personal_number' => $pigeon->personal_number,
                'gender' => $pigeon->gender,
                'color' => $pigeon->color,
                'status' => $pigeon->status,
                'hatch_date' => $pigeon->hatch_date?->format('Y-m-d'),
                'photo_url' => $pigeon->photo_url,
                'bloodline' => $pigeon->bloodlines->firstWhere('pivot.is_primary', true)?->name ?? $pigeon->bloodline,
                'merits' => $merits,
                'merit_score' => $pigeon->merits->sum(fn ($merit) => $merit->type->weight()),
                'results' => $results[$pigeon->id] ?? null,
            ]];
        })->all();
    }

    /**
     * Race record per pigeon across OLR and club races.
     *
     * @param  list<int>  $ids
     * @return array<int, array{flown: int, clocked: int, best: ?int, top10: int}>
     */
    public function raceRecords(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $records = [];

        $sources = [
            ['olr_race_results', 'olr_season_race_id', 'olr_season_races', 'olr_season_id', 'olr_seasons', 'olr_race_id', 'olr_races'],
            ['club_race_results', 'club_season_race_id', 'club_season_races', 'club_season_id', 'club_seasons', 'club_id', 'clubs'],
        ];

        foreach ($sources as [$table, $raceFk, $races, $seasonFk, $seasons, $ownerFk, $owners]) {
            // Only results in the bird owner's own seasons
            $rows = DB::table($table.' as result')
                ->join($races.' as race', 'race.id', '=', "result.{$raceFk}")
                ->join($seasons.' as season', 'season.id', '=', "race.{$seasonFk}")
                ->join($owners.' as owner', 'owner.id', '=', "season.{$ownerFk}")
                ->join('pigeons as bird', 'bird.id', '=', 'result.pigeon_id')
                ->whereColumn('bird.user_id', 'owner.user_id')
                ->whereIn('result.pigeon_id', $ids)
                ->groupBy('result.pigeon_id')
                ->selectRaw('result.pigeon_id as pigeon_id, count(*) as flown')
                ->selectRaw('sum(case when result.did_not_arrive = false and (result.arrival_time is not null or result.position is not null) then 1 else 0 end) as clocked')
                ->selectRaw('min(case when result.did_not_arrive then null else result.position end) as best')
                ->selectRaw('sum(case when result.did_not_arrive = false and result.position <= 10 then 1 else 0 end) as top10')
                ->get();

            foreach ($rows as $row) {
                $record = $records[$row->pigeon_id] ?? ['flown' => 0, 'clocked' => 0, 'best' => null, 'top10' => 0];
                $best = $row->best !== null ? (int) $row->best : null;

                $records[$row->pigeon_id] = [
                    'flown' => $record['flown'] + (int) $row->flown,
                    'clocked' => $record['clocked'] + (int) $row->clocked,
                    'best' => $record['best'] === null ? $best : ($best === null ? $record['best'] : min($record['best'], $best)),
                    'top10' => $record['top10'] + (int) $row->top10,
                ];
            }
        }

        return $records;
    }

    /**
     * Birds that descend from both $a and $b (for a pair, these are its offspring, grandchildren, ...),
     * ranked with proven birds (merits, race record) first, then closest relation.
     *
     * @return list<array{key: string, from_a: array{generation: int, via: list<string>, share: float}, from_b: array{generation: int, via: list<string>, share: float}, direct: bool}>
     */
    public function sharedDescendants(LoftGraph $graph, string $a, string $b): array
    {
        $fromA = $graph->descendants($a, self::DESCENDANT_GENERATIONS);
        $fromB = $graph->descendants($b, self::DESCENDANT_GENERATIONS);

        $shared = [];

        foreach (array_intersect_key($fromA, $fromB) as $key => $lineA) {
            $lineB = $fromB[$key];

            $shared[] = [
                'key' => $key,
                'from_a' => [...$lineA, 'share' => $graph->geneShare($a, $key)],
                'from_b' => [...$lineB, 'share' => $graph->geneShare($b, $key)],
                'direct' => $lineA['generation'] === 1 && $lineB['generation'] === 1,
            ];
        }

        return $shared;
    }

    /**
     * @return array<string, mixed>
     */
    public function sharedReport(LoftGraph $graph, string $a, string $b): array
    {
        $rows = $this->sharedDescendants($graph, $a, $b);

        $summaries = $this->summaries($graph, [
            ...array_column($rows, 'key'),
            ...array_merge([], ...array_map(fn ($row) => [...$row['from_a']['via'], ...$row['from_b']['via']], $rows)),
        ]);

        $line = fn (array $from) => [
            'generation' => $from['generation'],
            'label' => self::descendantLabel($from['generation']),
            'via' => array_map(fn ($via) => $this->mini($summaries[$via]), $from['via']),
            'share' => $from['share'],
        ];

        $birds = $this->rank(array_map(fn ($row) => [
            'bird' => $summaries[$row['key']],
            'direct' => $row['direct'],
            'from_a' => $line($row['from_a']),
            'from_b' => $line($row['from_b']),
        ], $rows), fn ($row) => $row['bird'], fn ($row) => $row['from_a']['generation'] + $row['from_b']['generation']);

        return [
            'total' => count($birds),
            'direct' => count(array_filter($birds, fn ($row) => $row['direct'])),
            'proven' => count(array_filter($birds, fn ($row) => $this->isProven($row['bird']))),
            'birds' => $birds,
        ];
    }

    public static function descendantLabel(int $generation): string
    {
        return match ($generation) {
            1 => 'Children',
            2 => 'Grandchildren',
            3 => 'Great-grandchildren',
            default => ($generation - 2).'× great-grandchildren',
        };
    }

    /**
     * Proven birds first (merits, then race record), then by closeness, then youngest first.
     *
     * @template T
     *
     * @param  list<T>  $rows
     * @param  (callable(T): array<string, mixed>)|null  $bird
     * @param  (callable(T): int)|null  $distance
     * @return list<T>
     */
    public function rank(array $rows, ?callable $bird = null, ?callable $distance = null): array
    {
        $bird ??= fn ($row) => $row;
        $distance ??= fn () => 0;

        usort($rows, function ($x, $y) use ($bird, $distance) {
            $bx = $bird($x);
            $by = $bird($y);

            return [$by['merit_score'], $by['results']['top10'] ?? 0, $by['results']['clocked'] ?? 0, $distance($x), $by['hatch_date'] ?? '']
                <=> [$bx['merit_score'], $bx['results']['top10'] ?? 0, $bx['results']['clocked'] ?? 0, $distance($y), $bx['hatch_date'] ?? ''];
        });

        return $rows;
    }

    /**
     * A bird with a merit or a top-10 race result.
     *
     * @param  array<string, mixed>  $bird
     */
    public function isProven(array $bird): bool
    {
        return $bird['merit_score'] > 0 || ($bird['results']['top10'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $bird
     * @return array{key: string, id: ?int, name: ?string, ring_number: ?string, external: bool}
     */
    public function mini(array $bird): array
    {
        return [
            'key' => $bird['key'],
            'id' => $bird['id'],
            'name' => $bird['name'],
            'ring_number' => $bird['ring_number'],
            'external' => $bird['external'],
        ];
    }
}
