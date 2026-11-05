<?php

namespace App\Services\Merits;

use App\Enums\MeritType;
use App\Models\Pigeon;
use App\Models\PigeonMerit;

/**
 * Golden Pair: a cock and hen whose youngsters include two or more birds with a winning merit
 * (Olympiad, Ace Pigeon, Overall Winner, Final Race Winner, Race Winner, Hot Spot Winner).
 *
 * It is worked out from the offspring's merits rather than entered, so it updates as merits are added.
 */
class GoldenPairs
{
    public const MIN_WINNERS = 2;

    /**
     * @param  iterable<array{0: int|null, 1: int|null}>  $pairs  [sire id, dam id]
     * @return array<string, array{offspring: int, winner_count: int, golden: bool, winners: list<array<string, mixed>>}> keyed by self::key()
     */
    public function forPairs(int $userId, iterable $pairs): array
    {
        $pairs = collect($pairs)
            ->filter(fn ($pair) => $pair[0] && $pair[1])
            ->map(fn ($pair) => [(int) $pair[0], (int) $pair[1]])
            ->unique(fn ($pair) => self::key(...$pair))
            ->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $offspring = Pigeon::query()
            ->where('user_id', $userId)
            ->whereIn('sire_id', $pairs->pluck(0)->unique())
            ->whereIn('dam_id', $pairs->pluck(1)->unique())
            ->with(['merits' => fn ($query) => $query->whereIn('type', array_map(fn (MeritType $type) => $type->value, MeritType::winners()))])
            ->get(['id', 'name', 'ring_number', 'personal_number', 'sire_id', 'dam_id'])
            ->groupBy(fn (Pigeon $pigeon) => self::key($pigeon->sire_id, $pigeon->dam_id));

        return $pairs->mapWithKeys(function ($pair) use ($offspring) {
            $key = self::key(...$pair);
            $children = $offspring->get($key, collect());
            $winners = $children
                ->filter(fn (Pigeon $child) => $child->merits->isNotEmpty())
                ->sortByDesc(fn (Pigeon $child) => $child->merits->max(fn (PigeonMerit $merit) => $merit->type->weight()))
                ->map(fn (Pigeon $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'ring_number' => $child->ring_number,
                    'personal_number' => $child->personal_number,
                    'merits' => $child->merits->map->summary()->values()->all(),
                ])
                ->values();

            return [$key => [
                'offspring' => $children->count(),
                'winner_count' => $winners->count(),
                'golden' => $winners->count() >= self::MIN_WINNERS,
                'winners' => $winners->all(),
            ]];
        })->all();
    }

    /**
     * Golden Pair status of one pair (empty record when either bird is missing).
     *
     * @return array{offspring: int, winner_count: int, golden: bool, winners: list<array<string, mixed>>}
     */
    public function forPair(int $userId, ?int $sireId, ?int $damId): array
    {
        return $this->forPairs($userId, [[$sireId, $damId]])[self::key((int) $sireId, (int) $damId)]
            ?? ['offspring' => 0, 'winner_count' => 0, 'golden' => false, 'winners' => []];
    }

    /**
     * Count the birds in a list of lineage summaries that hold a winning merit.
     *
     * @param  iterable<array{merits: list<array{type: string}>}>  $birds
     */
    public static function countWinners(iterable $birds): int
    {
        return collect($birds)
            ->filter(fn ($bird) => collect($bird['merits'] ?? [])->contains(fn ($merit) => MeritType::tryFrom($merit['type'])?->isWinner()))
            ->count();
    }

    public static function key(int $sireId, int $damId): string
    {
        return $sireId.':'.$damId;
    }
}
