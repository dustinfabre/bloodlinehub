<?php

namespace App\Enums;

/**
 * What kind of race a season race (lap / leg) is. Decides which merits its results can earn.
 */
enum RaceType: string
{
    case Regular = 'regular';
    case Training = 'training';
    case HotSpot = 'hot_spot';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular race / lap',
            self::Training => 'Training toss',
            self::HotSpot => 'Hot spot',
            self::Final => 'Final race',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Regular => 'A normal race or lap. Counts towards Superset and Finisher.',
            self::Training => 'Not a race. Ignored for merits.',
            self::HotSpot => 'A hot spot or pre-final race with its own prizes. Top 10 earns Hot Spot Winner.',
            self::Final => 'The final race of the derby or OLR. Top 10 earns Final Race Winner.',
        };
    }

    /**
     * Whether results in this race count as a lap of the season (for Superset / Finisher) and can earn merits.
     */
    public function countsForMerits(): bool
    {
        return $this !== self::Training;
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
        ], self::cases());
    }
}
