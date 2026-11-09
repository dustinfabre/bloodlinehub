<?php

namespace Tests\Unit;

use App\Support\RingSeries;
use PHPUnit\Framework\TestCase;

class RingSeriesTest extends TestCase
{
    public function test_splits_a_ring_into_series_and_number(): void
    {
        $this->assertSame(['series' => 'PHA-2026', 'number' => '1017'], RingSeries::parse('PHA-2026-1017'));
        $this->assertSame(['series' => 'PHA-2026', 'number' => '01017'], RingSeries::parse('PHA-2026-01017'));
        $this->assertSame(['series' => 'LGTV-2025', 'number' => '123'], RingSeries::parse('LGTV 2025 123'));
    }

    public function test_separators_and_case_do_not_change_the_series(): void
    {
        foreach (['PHA-2026-1017', 'pha 2026 1018', ' PHA/2026/1019 ', 'PHA 2026-1020'] as $ring) {
            $this->assertSame('PHA-2026', RingSeries::parse($ring)['series'], $ring);
        }
    }

    public function test_rings_without_a_trailing_number(): void
    {
        $this->assertNull(RingSeries::parse('PERSO'));
        $this->assertNull(RingSeries::parse('PHA-2026-12A'));
        $this->assertNull(RingSeries::parse(''));
        $this->assertNull(RingSeries::parse(null));
        // Digits only: a number with no series name
        $this->assertSame(['series' => '', 'number' => '12345'], RingSeries::parse('12345'));
    }
}
