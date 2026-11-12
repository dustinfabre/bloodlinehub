<?php

namespace App\Services\PedigreeImport;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Models\PigeonMerit;

/**
 * Turns what the reader returned into the review form's shape:
 *
 *   bird:      the bird itself (ring_number, name, gender, color, bloodline, bred_by, remarks, merits)
 *   ancestors: path => ring_number, name, color, bloodline, remarks, merits ("S", "SD", ... up to four letters)
 *   note:      what the reader could not read, for the user
 *
 * The reader's answer follows the schema, but every value is still checked and trimmed here: a bad value is left
 * out rather than failing the whole sheet. Outside ancestors have no "bred by" field, so an ancestor's breeder
 * becomes the first line of its remarks, the way pedigree sheets print it.
 */
final class PedigreeDraft
{
    private const MAX_DEPTH = 4;

    private const MAX_REMARKS = 2000;

    private const MAX_MERITS = 12;

    /**
     * The review form's data, or null when there is no pedigree to review.
     *
     * @param  array<string, mixed>  $reading
     * @return array{bird: array<string, mixed>, ancestors: array<string, array<string, mixed>>, note: ?string}|null
     */
    public static function fromReading(array $reading): ?array
    {
        if (($reading['is_pedigree'] ?? false) !== true) {
            return null;
        }

        // First record for each place wins
        $byPosition = [];
        foreach (is_array($reading['birds'] ?? null) ? $reading['birds'] : [] as $bird) {
            $position = is_array($bird) ? ($bird['position'] ?? null) : null;

            if (is_string($position) && ($position === 'bird' || preg_match('/^[SD]{1,'.self::MAX_DEPTH.'}$/', $position)) && ! isset($byPosition[$position])) {
                $byPosition[$position] = $bird;
            }
        }

        $subject = $byPosition['bird'] ?? [];
        unset($byPosition['bird']);

        $ancestors = [];
        foreach ($byPosition as $path => $bird) {
            $ancestor = [
                'ring_number' => self::ring($bird['ring_number'] ?? null),
                'name' => self::line($bird['name'] ?? null),
                'color' => self::line($bird['color'] ?? null),
                'bloodline' => self::line($bird['strain'] ?? null),
                'remarks' => self::remarks(self::line($bird['breeder'] ?? null), $bird['remarks'] ?? null),
                'merits' => self::merits($bird['merits'] ?? null),
            ];

            // An ancestor is saved by its ring number or name; a box with neither can't be kept
            if ($ancestor['ring_number'] || $ancestor['name']) {
                $ancestors[(string) $path] = $ancestor;
            }
        }

        uksort($ancestors, fn (string $a, string $b) => [strlen($a), $a] <=> [strlen($b), $b]);

        $sex = $reading['sex'] ?? null;

        $draft = [
            'bird' => [
                'ring_number' => self::ring($subject['ring_number'] ?? null),
                'name' => self::line($subject['name'] ?? null),
                'gender' => in_array($sex, ['male', 'female'], true) ? $sex : null,
                'color' => self::line($subject['color'] ?? null),
                'bloodline' => self::line($subject['strain'] ?? null),
                'bred_by' => self::line($subject['breeder'] ?? null),
                'remarks' => self::remarks(null, $subject['remarks'] ?? null),
                'merits' => self::merits($subject['merits'] ?? null),
            ],
            'ancestors' => $ancestors,
            'note' => self::line($reading['note'] ?? null, 500),
        ];

        // A sheet with no bird and no ancestors on it has nothing to review
        return $draft['bird']['ring_number'] || $draft['bird']['name'] || $ancestors ? $draft : null;
    }

    /**
     * Ancestors whose place can't be saved because the bird in front of them is missing: an ancestor is stored
     * behind its offspring, so "SS" needs "S".
     *
     * @param  array<string, mixed>  $ancestors
     * @return list<string>
     */
    public static function unreachable(array $ancestors): array
    {
        return array_values(array_filter(
            array_map('strval', array_keys($ancestors)),
            function (string $path) use ($ancestors) {
                for ($length = 1; $length < strlen($path); $length++) {
                    if (! isset($ancestors[substr($path, 0, $length)])) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    private static function ring(mixed $value): ?string
    {
        $ring = self::line($value);

        return $ring === null ? null : strtoupper($ring);
    }

    private static function line(mixed $value, int $max = 255): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $line = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        $line = trim($line, " \"'“”‘’");

        return $line === '' ? null : mb_substr($line, 0, $max);
    }

    private static function remarks(?string $breeder, mixed $value): ?string
    {
        $lines = is_string($value) ? preg_split('/\R/u', $value) : [];
        $lines = array_values(array_filter(
            array_map(fn (string $line) => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? ''), $lines),
            fn (string $line) => $line !== '',
        ));

        if ($breeder && ! in_array(mb_strtolower($breeder), array_map('mb_strtolower', $lines), true)) {
            array_unshift($lines, $breeder);
        }

        $text = implode("\n", $lines);

        return $text === '' ? null : mb_substr($text, 0, self::MAX_REMARKS);
    }

    /**
     * @return list<array{type: string, level: ?string, year: ?int, detail: ?string}>
     */
    private static function merits(mixed $value): array
    {
        $merits = [];
        $maxYear = now()->year + 1;

        foreach (is_array($value) ? $value : [] as $merit) {
            $type = is_array($merit) ? MeritType::tryFrom((string) ($merit['type'] ?? '')) : null;

            if (! $type) {
                continue;
            }

            $year = is_int($merit['year'] ?? null) && $merit['year'] >= 1900 && $merit['year'] <= $maxYear ? $merit['year'] : null;

            $merits[] = [
                'type' => $type->value,
                'level' => MeritLevel::tryFrom((string) ($merit['level'] ?? ''))?->value,
                'year' => $year,
                'detail' => self::line($merit['detail'] ?? null),
            ];
        }

        return PigeonMerit::cleanList(array_slice($merits, 0, self::MAX_MERITS)) ?? [];
    }
}
