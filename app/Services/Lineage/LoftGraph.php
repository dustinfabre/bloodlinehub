<?php

namespace App\Services\Lineage;

use App\Models\Pigeon;
use Illuminate\Support\Collection;

/**
 * The family graph of one loft: every loft bird plus every external ancestor recorded on a pedigree.
 *
 * Node keys:
 *  - "p:{id}"            a bird in the loft
 *  - "r:{RING}"          an external bird, identified by its normalised ring number, so the same
 *                        grandsire recorded on two different pedigrees is one node
 *  - "x:{anchor}:{path}" an external bird without a ring number; it cannot be matched to anything
 *
 * An external ring number that matches a loft bird resolves to that loft bird.
 */
final class LoftGraph
{
    /** Deepest pedigree path we follow for external ancestors ("SDSDSDSD"). */
    private const MAX_PATH = 8;

    /**
     * @var array<string, array{key: string, pigeon_id: ?int, external: bool, name: ?string, ring_number: ?string, color: ?string, gender: ?string, sire: ?string, dam: ?string}>
     */
    private array $nodes = [];

    /** @var array<string, list<string>> */
    private array $children = [];

    /** @var array<string, int> normalised ring => loft pigeon id */
    private array $ringIndex = [];

    /**
     * @param  Collection<int, Pigeon>  $pigeons
     * @param  Collection<int, Collection<string, object>>  $ancestorsByPigeon  pedigree_ancestors rows keyed by pigeon id, then path
     */
    public static function build(Collection $pigeons, Collection $ancestorsByPigeon): self
    {
        $graph = new self;

        foreach ($pigeons as $pigeon) {
            $graph->nodes['p:'.$pigeon->id] = [
                'key' => 'p:'.$pigeon->id,
                'pigeon_id' => $pigeon->id,
                'external' => false,
                'name' => $pigeon->name,
                'ring_number' => $pigeon->ring_number,
                'color' => $pigeon->color,
                'gender' => $pigeon->gender,
                'sire' => null,
                'dam' => null,
            ];

            if ($ring = self::normalizeRing($pigeon->ring_number)) {
                $graph->ringIndex[$ring] ??= $pigeon->id;
            }
        }

        foreach ($pigeons as $pigeon) {
            $key = 'p:'.$pigeon->id;
            $ancestors = $ancestorsByPigeon->get($pigeon->id, collect());

            foreach (['sire' => 'S', 'dam' => 'D'] as $side => $letter) {
                $parentId = $pigeon->{"{$side}_id"};

                $parentKey = $parentId && isset($graph->nodes['p:'.$parentId])
                    ? 'p:'.$parentId
                    : $graph->external($pigeon->id, $letter, [
                        'ring_number' => $pigeon->{"{$side}_ring_number"},
                        'name' => $pigeon->{"{$side}_name"},
                        'color' => $pigeon->{"{$side}_color"},
                        'merits' => $pigeon->{"{$side}_merits"},
                    ], $ancestors);

                if ($parentKey && $parentKey !== $key) {
                    $graph->setParent($key, $side, $parentKey);
                }
            }
        }

        return $graph;
    }

    public static function normalizeRing(?string $ring): ?string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $ring));

        return $normalized === '' ? null : $normalized;
    }

    public static function pigeonKey(int $id): string
    {
        return 'p:'.$id;
    }

    /**
     * The node key of a loft bird by pigeon id, or null if it is not in this loft.
     */
    public function loftBird(mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        $key = self::pigeonKey((int) $id);

        return isset($this->nodes[$key]) && ! $this->nodes[$key]['external'] ? $key : null;
    }

    public function has(string $key): bool
    {
        return isset($this->nodes[$key]);
    }

    /**
     * @return array{key: string, pigeon_id: ?int, external: bool, name: ?string, ring_number: ?string, color: ?string, gender: ?string, sire: ?string, dam: ?string}|null
     */
    public function node(string $key): ?array
    {
        return $this->nodes[$key] ?? null;
    }

    /**
     * @return array{0: ?string, 1: ?string} [sire key, dam key]
     */
    public function parentsOf(string $key): array
    {
        return [$this->nodes[$key]['sire'] ?? null, $this->nodes[$key]['dam'] ?? null];
    }

    /**
     * @return list<string>
     */
    public function childrenOf(string $key): array
    {
        return $this->children[$key] ?? [];
    }

    /**
     * Every descendant of $root within $maxGenerations, at its closest generation (1 = child),
     * with one shortest line of descent ("via" lists the birds between $root and the descendant).
     *
     * @return array<string, array{generation: int, via: list<string>}>
     */
    public function descendants(string $root, int $maxGenerations = 6): array
    {
        $found = [];
        $seen = [$root => true];
        $queue = [[$root, 0, []]];

        for ($i = 0; $i < count($queue); $i++) {
            [$key, $generation, $via] = $queue[$i];

            if ($generation >= $maxGenerations) {
                continue;
            }

            $childVia = $key === $root ? [] : [...$via, $key];

            foreach ($this->childrenOf($key) as $child) {
                if (isset($seen[$child])) {
                    continue;
                }

                $seen[$child] = true;
                $found[$child] = ['generation' => $generation + 1, 'via' => $childVia];
                $queue[] = [$child, $generation + 1, $childVia];
            }
        }

        return $found;
    }

    /**
     * The expected share of $ancestor's genes carried by $key: the sum of 0.5^n over every line from $key up to $ancestor.
     */
    public function geneShare(string $ancestor, string $key, int $maxGenerations = 8): float
    {
        $memo = [];

        return $this->shareOf($ancestor, $key, $maxGenerations, $memo);
    }

    /**
     * Inbreeding coefficient of $key from its recorded pedigree.
     */
    public function inbreeding(string $key, int $maxGenerations = 6): float
    {
        [$sire, $dam] = $this->parentsOf($key);

        return $sire && $dam && $maxGenerations > 1
            ? $this->mating($sire, $dam, $maxGenerations - 1)['coefficient']
            : 0.0;
    }

    /**
     * Wright's path coefficient for a (possibly hypothetical) youngster of $sire x $dam:
     * F = sum over common ancestors A, and over each pair of lines sire->A and dam->A that meet only at A,
     * of 0.5^(n1 + n2 + 1) * (1 + F_A).
     *
     * $maxGenerations counts up from the parents (1 = the parents' own parents).
     *
     * @return array{
     *     coefficient: float,
     *     common: array<string, array{contribution: float, lines: list<array{sire: string, dam: string}>}>,
     *     shares: array<string, array{share: float, positions: list<string>}>,
     *     completeness: list<array{generation: int, known: int, total: int}>
     * }
     */
    public function mating(string $sire, string $dam, int $maxGenerations = 5): array
    {
        $sireLines = $this->linesUp($sire, 'S', $maxGenerations);
        $damLines = $this->linesUp($dam, 'D', $maxGenerations);

        $sireByAncestor = [];
        foreach ($sireLines as $line) {
            $sireByAncestor[end($line['keys'])][] = $line;
        }

        $damByAncestor = [];
        foreach ($damLines as $line) {
            $damByAncestor[end($line['keys'])][] = $line;
        }

        $coefficient = 0.0;
        $common = [];
        $inbreedingMemo = [];

        foreach (array_intersect_key($sireByAncestor, $damByAncestor) as $ancestor => $fromSire) {
            foreach ($fromSire as $sireLine) {
                foreach ($damByAncestor[$ancestor] as $damLine) {
                    // The two lines may only meet at the common ancestor itself
                    if (count(array_intersect($sireLine['keys'], $damLine['keys'])) !== 1) {
                        continue;
                    }

                    $n1 = count($sireLine['keys']) - 1;
                    $n2 = count($damLine['keys']) - 1;
                    $budget = max(0, $maxGenerations - max($n1, $n2));
                    $ancestorF = $inbreedingMemo["{$ancestor}|{$budget}"] ??= $this->inbreeding($ancestor, $budget);
                    $term = 0.5 ** ($n1 + $n2 + 1) * (1 + $ancestorF);

                    $coefficient += $term;
                    $common[$ancestor]['contribution'] = ($common[$ancestor]['contribution'] ?? 0) + $term;
                    $common[$ancestor]['lines'][] = ['sire' => $sireLine['position'], 'dam' => $damLine['position']];
                }
            }
        }

        // How much of each ancestor the youngster carries: every appearance at generation g adds 0.5^g
        $shares = [];
        foreach ([...$sireLines, ...$damLines] as $line) {
            $ancestor = end($line['keys']);
            $shares[$ancestor]['share'] = ($shares[$ancestor]['share'] ?? 0) + 0.5 ** strlen($line['position']);
            $shares[$ancestor]['positions'][] = $line['position'];
        }

        // Known ancestors per generation of the youngster, to show how far the pedigree data reaches
        $completeness = [];
        for ($generation = 1; $generation <= $maxGenerations + 1; $generation++) {
            $known = count(array_filter([...$sireLines, ...$damLines], fn ($line) => strlen($line['position']) === $generation));
            $completeness[] = ['generation' => $generation, 'known' => $known, 'total' => 2 ** $generation];
        }

        uasort($common, fn ($a, $b) => $b['contribution'] <=> $a['contribution']);

        return [
            'coefficient' => $coefficient,
            'common' => $common,
            'shares' => $shares,
            'completeness' => $completeness,
        ];
    }

    /**
     * Every line from $start up through its ancestors, $start itself included.
     * "position" is the pedigree position as seen from a youngster of $start, e.g. "SDS" = sire's dam's sire.
     *
     * @return list<array{keys: list<string>, position: string}>
     */
    private function linesUp(string $start, string $startLetter, int $maxGenerations): array
    {
        $lines = [];
        $stack = [[[$start], $startLetter]];

        while ($stack) {
            [$keys, $position] = array_pop($stack);
            $lines[] = ['keys' => $keys, 'position' => $position];

            if (count($keys) > $maxGenerations) {
                continue;
            }

            [$sire, $dam] = $this->parentsOf(end($keys));

            foreach (['S' => $sire, 'D' => $dam] as $letter => $parent) {
                // Guard against loops in bad data (a bird recorded as its own ancestor)
                if ($parent && ! in_array($parent, $keys, true)) {
                    $stack[] = [[...$keys, $parent], $position.$letter];
                }
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, float>  $memo
     */
    private function shareOf(string $ancestor, string $key, int $depth, array &$memo): float
    {
        if ($key === $ancestor) {
            return 1.0;
        }

        if ($depth === 0) {
            return 0.0;
        }

        if (isset($memo[$key])) {
            return $memo[$key];
        }

        // Provisional value doubles as a loop guard
        $memo[$key] = 0.0;
        [$sire, $dam] = $this->parentsOf($key);

        return $memo[$key] = 0.5 * (
            ($sire ? $this->shareOf($ancestor, $sire, $depth - 1, $memo) : 0.0)
            + ($dam ? $this->shareOf($ancestor, $dam, $depth - 1, $memo) : 0.0)
        );
    }

    /**
     * @param  array{ring_number: ?string, name: ?string, color: ?string}  $details
     * @param  Collection<string, object>  $ancestors  the anchor bird's pedigree_ancestors keyed by path
     */
    private function external(int $anchorId, string $path, array $details, Collection $ancestors): ?string
    {
        if (! array_filter($details)) {
            return null;
        }

        $ring = self::normalizeRing($details['ring_number']);

        // The bird is in the loft after all: its own record supplies its parents
        if ($ring && isset($this->ringIndex[$ring])) {
            return 'p:'.$this->ringIndex[$ring];
        }

        $key = $ring ? 'r:'.$ring : "x:{$anchorId}:{$path}";

        $this->nodes[$key] ??= [
            'key' => $key,
            'pigeon_id' => null,
            'external' => true,
            'name' => $details['name'],
            'ring_number' => $details['ring_number'],
            'color' => $details['color'],
            'gender' => str_ends_with($path, 'S') ? 'male' : 'female',
            'merits' => [],
            'sire' => null,
            'dam' => null,
        ];

        // The same bird recorded in several pedigrees: take merits from whichever record has them
        if (! $this->nodes[$key]['merits'] && ! empty($details['merits'])) {
            $this->nodes[$key]['merits'] = $details['merits'];
        }

        if (strlen($path) >= self::MAX_PATH) {
            return $key;
        }

        foreach (['sire' => 'S', 'dam' => 'D'] as $side => $letter) {
            // The first pedigree that records this bird's parents wins
            if ($this->nodes[$key][$side] !== null || ! ($row = $ancestors->get($path.$letter))) {
                continue;
            }

            $parentKey = $this->external($anchorId, $path.$letter, [
                'ring_number' => $row->ring_number,
                'name' => $row->name,
                'color' => $row->color,
                'merits' => $row->merits,
            ], $ancestors);

            if ($parentKey && $parentKey !== $key) {
                $this->setParent($key, $side, $parentKey);
            }
        }

        return $key;
    }

    private function setParent(string $key, string $side, string $parentKey): void
    {
        $this->nodes[$key][$side] = $parentKey;

        if (! in_array($key, $this->children[$parentKey] ?? [], true)) {
            $this->children[$parentKey][] = $key;
        }
    }
}
