<?php

namespace App\Services\Lineage;

use App\Enums\MeritType;
use App\Models\PedigreeAncestor;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\Merits\GoldenPairs;
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
     * Public summaries (for a shared pedigree) leave out what only the owner should see: internal ids, which are
     * also in the node keys, loft numbers, statuses, and photos (relatives' photos are public only when the owner
     * features them on the pedigree). Results stay keyed by the real node key.
     *
     * @param  iterable<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    public function summaries(LoftGraph $graph, iterable $keys, bool $public = false): array
    {
        if ($public) {
            return array_map(fn (array $summary) => [
                ...$summary,
                'key' => self::publicKey($summary['key']),
                'id' => null,
                'personal_number' => null,
                'status' => null,
                'photo_url' => null,
            ], $this->summaries($graph, $keys));
        }

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
     * A node key that doesn't give away pigeon ids ("p:12", "x:12:SD"), the same for one bird throughout a response.
     */
    public static function publicKey(string $key): string
    {
        return 'b:'.substr(hash_hmac('sha256', $key, (string) config('app.key')), 0, 16);
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
     * The birds that descend from both $a and $b, ready to show. $exclude leaves one of them out, e.g. the bird whose
     * pedigree shows the report for its own parents.
     *
     * @return array<string, mixed>
     */
    public function sharedReport(LoftGraph $graph, string $a, string $b, bool $public = false, ?string $exclude = null): array
    {
        $rows = array_values(array_filter(
            $this->sharedDescendants($graph, $a, $b),
            fn (array $row) => $row['key'] !== $exclude,
        ));

        $summaries = $this->summaries($graph, [
            ...array_column($rows, 'key'),
            ...array_merge([], ...array_map(fn ($row) => [...$row['from_a']['via'], ...$row['from_b']['via']], $rows)),
        ], $public);

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

    /**
     * A bird's family: its parents, its descendants down to DESCENDANT_GENERATIONS, its full and half siblings
     * (bought-in parents matched by ring number) and its mates with what they produced.
     *
     * @return array{bird: array<string, mixed>, family: array<string, mixed>}
     */
    public function family(LoftGraph $graph, string $key, bool $public = false): array
    {
        $descendants = $graph->descendants($key, self::DESCENDANT_GENERATIONS);
        [$sire, $dam] = $graph->parentsOf($key);

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

        $summaries = $this->summaries($graph, [
            $key, ...array_filter([$sire, $dam]),
            ...array_keys($descendants),
            ...array_merge([], ...array_column($descendants, 'via')),
            ...$full, ...$halfSire, ...$halfDam,
            ...array_filter(array_keys($mates), fn ($mate) => $mate !== 'unknown'),
        ], $public);

        $generations = [];
        foreach ($descendants as $descendantKey => $line) {
            [$childSire, $childDam] = $graph->parentsOf($descendantKey);
            $otherParent = $line['generation'] === 1 ? ($childSire === $key ? $childDam : $childSire) : null;

            $generations[$line['generation']][] = [
                'bird' => $summaries[$descendantKey],
                'via' => array_map(fn ($via) => $this->mini($summaries[$via]), $line['via']),
                'other_parent' => $otherParent ? $this->mini($summaries[$otherParent]) : null,
                'share' => $graph->geneShare($key, $descendantKey),
            ];
        }
        ksort($generations);

        return [
            'bird' => $summaries[$key],
            'family' => [
                'sire' => $sire ? $summaries[$sire] : null,
                'dam' => $dam ? $summaries[$dam] : null,
                'descendants' => collect($generations)->map(fn ($birds, $generation) => [
                    'generation' => $generation,
                    'label' => self::descendantLabel($generation),
                    'birds' => $this->rank($birds, fn ($row) => $row['bird']),
                ])->values()->all(),
                'siblings' => [
                    'full' => $this->rank(array_map(fn ($k) => $summaries[$k], $full)),
                    'sire_side' => $this->rank(array_map(fn ($k) => $summaries[$k], $halfSire)),
                    'dam_side' => $this->rank(array_map(fn ($k) => $summaries[$k], $halfDam)),
                ],
                'mates' => collect($mates)
                    ->map(function ($children, $mate) use ($summaries) {
                        $winners = GoldenPairs::countWinners(array_map(fn ($child) => $summaries[$child], $children));

                        return [
                            'bird' => $mate === 'unknown' ? null : $summaries[$mate],
                            'offspring' => count($children),
                            'proven' => count(array_filter($children, fn ($child) => $this->isProven($summaries[$child]))),
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
