<?php

namespace App\Services\Lineage;

use App\Services\Merits\GoldenPairs;

/**
 * Pairing Analysis: how related a sire and a dam are, which ancestors their youngsters are line-bred on, and how the
 * birds that already carry both have done. Shown on the Pairing Analysis page for a planned pair, and on a pedigree
 * for the pair that bred the bird.
 */
class PairingAnalysis
{
    /** Common ancestors named on the pedigree sheet, biggest contribution first. */
    private const SHEET_ANCESTORS = 3;

    public function __construct(
        private LineageService $lineage,
        private GoldenPairs $goldenPairs,
    ) {}

    /**
     * The full analysis of $sire × $dam (graph keys of birds with recorded parents or not).
     *
     * Public analyses leave out internal ids, loft numbers and statuses (see LineageService::summaries()).
     * $subject is the bird whose pedigree this is: one of the pair's youngsters, left out of the birds that carry both.
     *
     * @return array<string, mixed>
     */
    public function analyse(LoftGraph $graph, string $sire, string $dam, int $userId, bool $public = false, ?string $subject = null): array
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

        $summaries = $this->lineage->summaries($graph, [$sire, $dam, ...array_keys($common), ...$influential->keys()], $public);

        return [
            'sire' => $summaries[$sire],
            'dam' => $summaries[$dam],
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
            'shared' => $this->lineage->sharedReport($graph, $sire, $dam, $public, $subject),
            'golden_pair' => $this->goldenPair($graph, $sire, $dam, $userId, $public),
        ];
    }

    /**
     * The COI of a youngster of $sire × $dam as printed on its pedigree sheet: the figure, what it compares to,
     * the common ancestors that make it up and how much of the pedigree is known.
     *
     * @return array{
     *     coefficient: float,
     *     assessment: array{level: string, label: string, equivalent: string},
     *     common_ancestors: list<array{name: ?string, ring_number: ?string, contribution: float}>,
     *     common_count: int,
     *     generations: int,
     *     complete_generations: int,
     * }
     */
    public function sheet(LoftGraph $graph, string $sire, string $dam): array
    {
        $mating = $graph->mating($sire, $dam, LineageService::PEDIGREE_GENERATIONS);

        // Deepest generation of the youngster's ancestors where every bird is known
        $complete = 0;
        foreach ($mating['completeness'] as $row) {
            if ($row['known'] < $row['total']) {
                break;
            }
            $complete = $row['generation'];
        }

        return [
            'coefficient' => $mating['coefficient'],
            'assessment' => self::assess($mating['coefficient']),
            'common_ancestors' => collect($mating['common'])
                ->take(self::SHEET_ANCESTORS)
                ->map(fn ($row, $key) => [
                    'name' => $graph->node($key)['name'],
                    'ring_number' => $graph->node($key)['ring_number'],
                    'contribution' => $row['contribution'],
                ])
                ->values()
                ->all(),
            'common_count' => count($mating['common']),
            'generations' => count($mating['completeness']),
            'complete_generations' => $complete,
        ];
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

    /**
     * Golden Pair status, for two loft birds only. Public analyses keep the count, not the winners' records.
     *
     * @return array{offspring: int, winner_count: int, golden: bool, winners: list<array<string, mixed>>}
     */
    private function goldenPair(LoftGraph $graph, string $sire, string $dam, int $userId, bool $public): array
    {
        $sireId = $graph->node($sire)['pigeon_id'];
        $damId = $graph->node($dam)['pigeon_id'];

        if (! $sireId || ! $damId) {
            return ['offspring' => 0, 'winner_count' => 0, 'golden' => false, 'winners' => []];
        }

        $pair = $this->goldenPairs->forPair($userId, $sireId, $damId);

        return $public ? [...$pair, 'winners' => []] : $pair;
    }
}
