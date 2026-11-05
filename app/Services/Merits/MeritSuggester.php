<?php

namespace App\Services\Merits;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Enums\RaceType;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Suggests merits from what the loft already records: race results (OLR and club seasons) and the merits of a
 * bird's offspring. Nothing is added automatically; the breeder adds or dismisses each suggestion.
 *
 * Rules (the same for every loft):
 *  - Race Winner: 1st in a regular race.
 *  - Final Race Winner / Hot Spot Winner: top 10 in a race marked as the final / a hot spot.
 *  - Prize Winner: in the top 20% of the birds released (needs birds released on the race), once per season.
 *  - Overall Winner: overall position 1–10 recorded on the season entry.
 *  - Superset Winner: clocked in every lap of a completed season (two laps or more).
 *  - Finisher: clocked in some laps of a completed season, but not all.
 *  - Breeder of Winners: two or more children or grandchildren hold a winning merit.
 * Training tosses never count.
 */
class MeritSuggester
{
    public const TOP_PLACES = 10;

    public const PRIZE_SHARE = 0.2;

    public const MIN_LAPS = 2;

    public const BREEDER_MIN_WINNERS = 2;

    private const KINDS = [
        'olr' => [
            'results' => 'olr_race_results',
            'race_fk' => 'olr_season_race_id',
            'races' => 'olr_season_races',
            'season_fk' => 'olr_season_id',
            'seasons' => 'olr_seasons',
            'entries' => 'olr_season_entries',
            'owners' => 'olr_races',
            'owner_fk' => 'olr_race_id',
            'level' => MeritLevel::Olr,
        ],
        'club' => [
            'results' => 'club_race_results',
            'race_fk' => 'club_season_race_id',
            'races' => 'club_season_races',
            'season_fk' => 'club_season_id',
            'seasons' => 'club_seasons',
            'entries' => 'club_season_entries',
            'owners' => 'clubs',
            'owner_fk' => 'club_id',
            'level' => MeritLevel::Club,
        ],
    ];

    /**
     * Open suggestions for these loft birds (already checked to belong to $userId), keyed by pigeon id.
     *
     * @param  array<int, int>  $pigeonIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forPigeons(int $userId, array $pigeonIds): array
    {
        $pigeonIds = array_values(array_unique(array_map('intval', $pigeonIds)));

        if (! $pigeonIds) {
            return [];
        }

        $found = [];

        $sources = [
            ...array_map(fn (string $kind) => $this->racing($kind, $pigeonIds)['suggestions'], array_keys(self::KINDS)),
            $this->breeding($userId, $pigeonIds),
        ];

        foreach ($sources as $suggestions) {
            foreach ($suggestions as $pigeonId => $list) {
                $found[$pigeonId] = [...$found[$pigeonId] ?? [], ...$list];
            }
        }

        return $this->open($found);
    }

    /**
     * One season's standings: laps clocked, best position and overall position for each entry, with its open
     * suggestions from this season.
     *
     * @param  'olr'|'club'  $kind
     * @return array<int, array{laps: int, clocked: int, best: ?int, overall_position: ?int, suggestions: list<array<string, mixed>>}>
     */
    public function season(string $kind, int $seasonId): array
    {
        $tables = self::KINDS[$kind];

        $entries = $this->ownersRows(DB::table($tables['entries'].' as entry'), $tables, 'entry')
            ->where('entry.'.$tables['season_fk'], $seasonId)
            ->pluck('entry.overall_position', 'entry.pigeon_id');

        if ($entries->isEmpty()) {
            return [];
        }

        $racing = $this->racing($kind, $entries->keys()->map(fn ($id) => (int) $id)->all(), $seasonId);
        $open = $this->open($racing['suggestions']);
        $laps = $racing['laps'][$seasonId] ?? 0;

        return $entries->mapWithKeys(function ($overall, $pigeonId) use ($racing, $open, $laps, $seasonId) {
            $stats = $racing['stats'][$pigeonId][$seasonId] ?? ['clocked' => 0, 'best' => null];

            return [(int) $pigeonId => [
                'laps' => $laps,
                'clocked' => $stats['clocked'],
                'best' => $stats['best'],
                'overall_position' => $overall !== null ? (int) $overall : null,
                'suggestions' => $open[$pigeonId] ?? [],
            ]];
        })->all();
    }

    /**
     * Suggestions from race results, plus per-season stats.
     *
     * @param  'olr'|'club'  $kind
     * @param  list<int>  $pigeonIds
     * @return array{suggestions: array<int, list<array>>, stats: array<int, array<int, array{clocked: int, best: ?int}>>, laps: array<int, int>}
     */
    private function racing(string $kind, array $pigeonIds, ?int $seasonId = null): array
    {
        $tables = self::KINDS[$kind];
        $level = $tables['level'];

        $rows = $this->ownersRows(
            DB::table($tables['results'].' as result')
                ->join($tables['races'].' as race', 'race.id', '=', 'result.'.$tables['race_fk'])
                ->join($tables['seasons'].' as season', 'season.id', '=', 'race.'.$tables['season_fk']),
            $tables,
            'result',
        )
            ->whereIn('result.pigeon_id', $pigeonIds)
            ->where('season.status', '!=', 'cancelled')
            ->when($seasonId, fn ($query) => $query->where('season.id', $seasonId))
            ->orderBy('race.race_date')
            ->orderBy('race.id')
            ->get([
                'result.pigeon_id', 'result.position', 'result.arrival_time', 'result.did_not_arrive',
                'race.id as race_id', 'race.name as race_name', 'race.release_point', 'race.race_type', 'race.birds_released',
                'season.id as season_id', 'season.name as season_name', 'season.year', 'season.status as season_status',
            ]);

        $overall = $this->ownersRows(DB::table($tables['entries'].' as entry'), $tables, 'entry')
            ->whereIn('entry.pigeon_id', $pigeonIds)
            ->whereNotNull('entry.overall_position')
            ->where('season.status', '!=', 'cancelled')
            ->when($seasonId, fn ($query) => $query->where('season.id', $seasonId))
            ->get(['entry.pigeon_id', 'entry.overall_position', 'season.id as season_id', 'season.name as season_name', 'season.year']);

        $laps = $this->laps($kind, $rows->pluck('season_id')->merge($seasonId ? [$seasonId] : [])->unique()->all());

        $suggestions = [];
        $stats = [];

        foreach ($rows->groupBy('pigeon_id') as $pigeonId => $pigeonRows) {
            $pigeonId = (int) $pigeonId;

            foreach ($pigeonRows->groupBy('season_id') as $seasonKey => $seasonRows) {
                $season = $seasonRows->first();
                $year = (int) $season->year ?: null;
                $prizes = [];
                $clockedLaps = [];
                $best = null;

                foreach ($seasonRows as $row) {
                    $type = RaceType::tryFrom((string) $row->race_type) ?? RaceType::Regular;

                    if (! $type->countsForMerits() || ! self::clocked($row)) {
                        continue;
                    }

                    $clockedLaps[$row->race_id] = true;
                    $position = $row->position !== null ? (int) $row->position : null;

                    if ($position === null) {
                        continue;
                    }

                    $best = $best === null ? $position : min($best, $position);
                    $place = self::place($position, $row->birds_released);
                    $race = self::raceLabel($row, $type);
                    $source = "{$kind}_race:{$row->race_id}";

                    $raceMerit = match (true) {
                        $type === RaceType::Final && $position <= self::TOP_PLACES => [MeritType::FinalRaceWinner, "{$place} in the final"],
                        $type === RaceType::HotSpot && $position <= self::TOP_PLACES => [MeritType::HotSpotWinner, "{$place} in the hot spot"],
                        $type === RaceType::Regular && $position === 1 => [MeritType::RaceWinner, $place],
                        default => null,
                    };

                    if ($raceMerit) {
                        [$meritType, $what] = $raceMerit;
                        $suggestions[$pigeonId][] = $this->suggestion(
                            $pigeonId, $meritType, $level, $year,
                            "{$what} · {$race} · {$season->season_name}",
                            $source,
                            ucfirst("{$what}, {$race}"),
                        );

                        continue;
                    }

                    if ($row->birds_released && $position <= (int) ceil($row->birds_released * self::PRIZE_SHARE)) {
                        $prizes[] = "{$race} ({$place})";
                    }
                }

                $stats[$pigeonId][(int) $seasonKey] = ['clocked' => count($clockedLaps), 'best' => $best];

                if ($prizes) {
                    $times = count($prizes) === 1 ? 'once' : count($prizes).'×';
                    $suggestions[$pigeonId][] = $this->suggestion(
                        $pigeonId, MeritType::PrizeWinner, $level, $year,
                        "In the prizes {$times} · {$season->season_name}",
                        "{$kind}_season:{$season->season_id}",
                        'Top 20% in '.implode(', ', $prizes),
                    );
                }

                // A series is only judged once the season is over
                $total = $laps[(int) $seasonKey] ?? 0;
                $clocked = count($clockedLaps);

                if ($season->season_status === 'completed' && $total >= self::MIN_LAPS && $clocked > 0) {
                    $superset = $clocked >= $total;
                    $suggestions[$pigeonId][] = $this->suggestion(
                        $pigeonId, $superset ? MeritType::Superset : MeritType::Finisher, $level, $year,
                        "Clocked {$clocked}/{$total} laps · {$season->season_name}",
                        "{$kind}_season:{$season->season_id}",
                        $superset ? "Clocked in all {$total} laps" : "Clocked in {$clocked} of {$total} laps",
                    );
                }
            }
        }

        foreach ($overall as $row) {
            $position = (int) $row->overall_position;

            if ($position < 1 || $position > self::TOP_PLACES) {
                continue;
            }

            $place = self::ordinal($position);
            $suggestions[(int) $row->pigeon_id][] = $this->suggestion(
                (int) $row->pigeon_id, MeritType::OverallWinner, $level, (int) $row->year ?: null,
                "{$place} overall · {$row->season_name}",
                "{$kind}_season:{$row->season_id}",
                "{$place} in the overall standings",
            );
        }

        return ['suggestions' => $suggestions, 'stats' => $stats, 'laps' => $laps];
    }

    /**
     * Laps per season: races that count for merits and have results recorded (laps not flown yet don't count).
     *
     * @param  'olr'|'club'  $kind
     * @param  array<int, int>  $seasonIds
     * @return array<int, int>
     */
    private function laps(string $kind, array $seasonIds): array
    {
        if (! $seasonIds) {
            return [];
        }

        $tables = self::KINDS[$kind];

        return DB::table($tables['races'].' as race')
            ->join($tables['seasons'].' as season', 'season.id', '=', 'race.'.$tables['season_fk'])
            ->join($tables['owners'].' as owner', 'owner.id', '=', 'season.'.$tables['owner_fk'])
            ->whereIn('race.'.$tables['season_fk'], $seasonIds)
            ->where('race.race_type', '!=', RaceType::Training->value)
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from($tables['results'].' as result')
                ->join('pigeons as bird', 'bird.id', '=', 'result.pigeon_id')
                ->whereColumn('result.'.$tables['race_fk'], 'race.id')
                ->whereColumn('bird.user_id', 'owner.user_id'))
            ->groupBy('race.'.$tables['season_fk'])
            ->selectRaw('race.'.$tables['season_fk'].' as season_id, count(*) as laps')
            ->pluck('laps', 'season_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Limit season rows (entries or results, aliased $alias) to birds owned by the season's owner. Rows written
     * before nested routes were scoped could link one user's bird to another user's season.
     */
    private function ownersRows(\Illuminate\Database\Query\Builder $query, array $tables, string $alias): \Illuminate\Database\Query\Builder
    {
        $joined = collect($query->joins ?? [])->contains(fn ($join) => str_ends_with((string) $join->table, ' as season'));

        if (! $joined) {
            $query->join($tables['seasons'].' as season', 'season.id', '=', "{$alias}.".$tables['season_fk']);
        }

        return $query
            ->join($tables['owners'].' as owner', 'owner.id', '=', 'season.'.$tables['owner_fk'])
            ->join('pigeons as bird', 'bird.id', '=', "{$alias}.pigeon_id")
            ->whereColumn('bird.user_id', 'owner.user_id');
    }

    /**
     * Breeder of Winners: two or more children or grandchildren in the loft hold a winning merit.
     *
     * @param  list<int>  $pigeonIds
     * @return array<int, list<array>>
     */
    private function breeding(int $userId, array $pigeonIds): array
    {
        $children = $this->childrenOf($userId, $pigeonIds);
        $grandchildren = $this->childrenOf($userId, $children->pluck('id')->all());

        $winners = PigeonMerit::query()
            ->whereIn('pigeon_id', $children->pluck('id')->merge($grandchildren->pluck('id'))->unique())
            ->whereIn('type', array_map(fn (MeritType $type) => $type->value, MeritType::winners()))
            ->distinct()
            ->pluck('pigeon_id')
            ->flip();

        $suggestions = [];

        foreach ($pigeonIds as $pigeonId) {
            $ownChildren = $children->filter(fn ($child) => (int) $child->sire_id === $pigeonId || (int) $child->dam_id === $pigeonId);
            $childIds = $ownChildren->pluck('id')->flip();
            $ownGrandchildren = $grandchildren
                ->filter(fn ($grandchild) => isset($childIds[$grandchild->sire_id]) || isset($childIds[$grandchild->dam_id]))
                ->reject(fn ($grandchild) => isset($childIds[$grandchild->id]));

            $winningChildren = $ownChildren->filter(fn ($child) => isset($winners[$child->id]))->count();
            $winningGrandchildren = $ownGrandchildren->filter(fn ($grandchild) => isset($winners[$grandchild->id]))->count();

            if ($winningChildren + $winningGrandchildren < self::BREEDER_MIN_WINNERS) {
                continue;
            }

            $detail = implode(', ', array_filter([
                $winningChildren ? 'Parent of '.$winningChildren.' '.Str::plural('winner', $winningChildren) : null,
                $winningGrandchildren ? ($winningChildren ? 'grandparent of ' : 'Grandparent of ').$winningGrandchildren.' '.Str::plural('winner', $winningGrandchildren) : null,
            ]));

            $suggestions[$pigeonId][] = $this->suggestion(
                $pigeonId, MeritType::BreederOfWinners, null, null, $detail, 'offspring', $detail,
            );
        }

        return $suggestions;
    }

    /**
     * @param  array<int, int>  $parentIds
     * @return Collection<int, Pigeon>
     */
    private function childrenOf(int $userId, array $parentIds): Collection
    {
        if (! $parentIds) {
            return collect();
        }

        return Pigeon::query()
            ->where('user_id', $userId)
            ->where(fn ($query) => $query->whereIn('sire_id', $parentIds)->orWhereIn('dam_id', $parentIds))
            ->get(['id', 'sire_id', 'dam_id']);
    }

    /**
     * Drop suggestions the bird already holds or the breeder dismissed, and sort the rest by significance.
     *
     * @param  array<int, list<array>>  $found
     * @return array<int, list<array>>
     */
    private function open(array $found): array
    {
        $found = array_filter($found);

        if (! $found) {
            return [];
        }

        $ids = array_keys($found);
        $merits = PigeonMerit::whereIn('pigeon_id', $ids)->get(['pigeon_id', 'type', 'level', 'year', 'source'])->groupBy('pigeon_id');
        $dismissed = DB::table('merit_suggestion_dismissals')
            ->whereIn('pigeon_id', $ids)
            ->get(['pigeon_id', 'suggestion'])
            ->groupBy('pigeon_id')
            ->map(fn ($rows) => $rows->pluck('suggestion')->flip());

        $open = [];

        foreach ($found as $pigeonId => $list) {
            $held = $merits->get($pigeonId, collect());
            $skip = $dismissed->get($pigeonId, collect());
            $seen = [];

            foreach ($list as $suggestion) {
                if (isset($seen[$suggestion['key']]) || $skip->has($suggestion['suggestion'])) {
                    continue;
                }
                $seen[$suggestion['key']] = true;

                $merit = $suggestion['merit'];
                $alreadyHeld = $held->contains(fn (PigeonMerit $existing) => $existing->type->value === $merit['type'] && (
                    $existing->source === $suggestion['source']
                    // Entered by hand before suggestions existed: same merit, level and year
                    || ($existing->source === null && $existing->level?->value === $merit['level'] && $existing->year === $merit['year'])
                    // A bird is a Breeder of Winners once, however many winners it has
                    || $existing->type === MeritType::BreederOfWinners
                ));

                if (! $alreadyHeld) {
                    $open[$pigeonId][] = $suggestion;
                }
            }

            if (isset($open[$pigeonId])) {
                usort($open[$pigeonId], fn ($a, $b) => [MeritType::from($b['merit']['type'])->weight(), $b['merit']['year'] ?? 0]
                    <=> [MeritType::from($a['merit']['type'])->weight(), $a['merit']['year'] ?? 0]);
            }
        }

        return $open;
    }

    private function suggestion(int $pigeonId, MeritType $type, ?MeritLevel $level, ?int $year, string $detail, string $source, string $reason): array
    {
        $suggestion = $type->value.':'.$source;

        return [
            'key' => $pigeonId.':'.$suggestion,
            'suggestion' => $suggestion,
            'pigeon_id' => $pigeonId,
            'source' => $source,
            'reason' => $reason,
            'merit' => PigeonMerit::summarize($type, $level, $year, Str::limit($detail, 250)),
        ];
    }

    /**
     * Clocked in race time: not marked as not arrived, and an arrival time or position recorded.
     * (Rows added for every entry before a race is flown have neither.)
     */
    public static function clocked(object $row): bool
    {
        return ! $row->did_not_arrive && ($row->arrival_time !== null || $row->position !== null);
    }

    /**
     * "Lap 3, Tarlac". A race simply named after its type ("Final", "Hot Spot") is left to the merit's own wording.
     */
    private static function raceLabel(object $row, RaceType $type): string
    {
        $name = strtolower(str_replace('-', ' ', $row->race_name));
        $point = $row->release_point && ! str_contains($name, strtolower($row->release_point))
            ? $row->release_point
            : null;
        $namedForType = ($type === RaceType::Final && str_contains($name, 'final'))
            || ($type === RaceType::HotSpot && str_contains($name, 'hot spot'));

        return implode(', ', array_filter([$namedForType && $point ? null : $row->race_name, $point]));
    }

    private static function place(int $position, mixed $birdsReleased): string
    {
        return self::ordinal($position).($birdsReleased ? ' of '.number_format((int) $birdsReleased) : '');
    }

    public static function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }
}
