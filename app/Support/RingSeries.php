<?php

namespace App\Support;

/**
 * Ring series: the birds of a team usually wear rings from one series, e.g. PHA-2026-1001 to PHA-2026-1050.
 */
final class RingSeries
{
    /**
     * Split a ring number into its series and its number: "PHA-2026-01017" is number 01017 of series PHA-2026.
     * Separators don't matter, so "pha 2026 1017" is in the same series. A ring of digits only has the series ''.
     * A ring that doesn't end in a number has no series.
     *
     * @return array{series: string, number: string}|null
     */
    public static function parse(?string $ring): ?array
    {
        $ring = strtoupper(trim((string) $ring));

        if (! preg_match('/^(.*?)(\d+)$/', $ring, $matches)) {
            return null;
        }

        return [
            'series' => trim(preg_replace('/[^A-Z0-9]+/', '-', $matches[1]), '-'),
            'number' => $matches[2],
        ];
    }
}
