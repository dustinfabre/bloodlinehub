<?php

namespace App\Enums;

/**
 * The fixed merit vocabulary. Merits mean the same thing for every loft, so they can be searched, ranked and
 * compared across bloodlines, and each one has the same color on every pedigree.
 *
 * Names follow international pedigree / auction usage, plus the Philippine derby & OLR terms
 * (Overall Winner, Superset Winner, Finisher) that have no international equivalent.
 */
enum MeritType: string
{
    // Racing / performance
    case Olympiad = 'olympiad';
    case AcePigeon = 'ace_pigeon';
    case OverallWinner = 'overall_winner';
    case FinalRaceWinner = 'final_race_winner';
    case RaceWinner = 'race_winner';
    case HotSpotWinner = 'hot_spot_winner';
    case Superset = 'superset';
    case PrizeWinner = 'prize_winner';
    case Finisher = 'finisher';

    // Breeding / producer
    case SuperBreeder = 'super_breeder';
    case BreederOfWinners = 'breeder_of_winners';
    case Foundation = 'foundation';

    public function label(): string
    {
        return match ($this) {
            self::Olympiad => 'Olympiad / National Title',
            self::AcePigeon => 'Ace Pigeon',
            self::OverallWinner => 'Overall Winner',
            self::FinalRaceWinner => 'Final Race Winner',
            self::RaceWinner => 'Race Winner',
            self::HotSpotWinner => 'Hot Spot Winner',
            self::Superset => 'Superset Winner',
            self::PrizeWinner => 'Prize Winner',
            self::Finisher => 'Finisher',
            self::SuperBreeder => 'Super Breeder',
            self::BreederOfWinners => 'Breeder of Winners',
            self::Foundation => 'Foundation Bird',
        };
    }

    /**
     * Short code for badges and pedigree cards.
     */
    public function code(): string
    {
        return match ($this) {
            self::Olympiad => 'OLY',
            self::AcePigeon => 'ACE',
            self::OverallWinner => 'OA',
            self::FinalRaceWinner => 'FR',
            self::RaceWinner => '1st',
            self::HotSpotWinner => 'HS',
            self::Superset => 'SS',
            self::PrizeWinner => 'P',
            self::Finisher => 'FIN',
            self::SuperBreeder => 'SB',
            self::BreederOfWinners => 'BoW',
            self::Foundation => 'BASE',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Olympiad => 'Selected for the FCI Olympiad, or holds a national or federation championship title.',
            self::AcePigeon => 'Top-ranked over a whole series or season (best combined results across several races).',
            self::OverallWinner => 'Placed in the top 10 of the overall standings of a derby or OLR (all laps completed).',
            self::FinalRaceWinner => 'Placed in the top 10 of the final race of a derby or OLR.',
            self::RaceWinner => 'Won a race outright (1st place) at any level.',
            self::HotSpotWinner => 'Placed in the top 10 of a hot spot / pre-final race of a derby or OLR.',
            self::Superset => 'Clocked in race time in every lap (leg) of a race series.',
            self::PrizeWinner => 'Placed in the prizes: the top 20% of the birds released.',
            self::Finisher => 'Clocked in some laps of a race series, but not all of them.',
            self::SuperBreeder => 'Proven producer: has bred several top birds (winners, aces, overall winners).',
            self::BreederOfWinners => 'Parent or grandparent of race winners or other titled birds.',
            self::Foundation => 'A cornerstone bird the loft\'s family is built on.',
        };
    }

    /**
     * Winning merits: what makes a pair a Golden Pair and a parent a Breeder of Winners.
     * Superset, Prize Winner and Finisher show a good bird, but not a winner.
     */
    public function isWinner(): bool
    {
        return in_array($this, self::winners(), true);
    }

    /**
     * @return list<self>
     */
    public static function winners(): array
    {
        return [
            self::Olympiad,
            self::AcePigeon,
            self::OverallWinner,
            self::FinalRaceWinner,
            self::RaceWinner,
            self::HotSpotWinner,
        ];
    }

    /**
     * Fixed chip color, the same for every loft. Hue is the kind of merit, shade its rank within that kind
     * (darkest = most significant):
     *  - gold: titles (Olympiad, Ace, Overall)
     *  - red: race wins (final, race, hot spot)
     *  - teal: steady flying (superset, prizes, finisher)
     *  - violet: breeding (super breeder, breeder of winners, foundation)
     *
     * Chip text is white or navy, whichever reads better; every pair stays at least 4.5:1. Checked for
     * color-blind separation too, but the code on each chip is what identifies the merit.
     */
    public function color(): string
    {
        return match ($this) {
            self::Olympiad => '#916600',
            self::AcePigeon => '#D39900',
            self::OverallWinner => '#EBC75E',
            self::FinalRaceWinner => '#9A1C32',
            self::RaceWinner => '#D0363B',
            self::HotSpotWinner => '#F8907C',
            self::Superset => '#00666C',
            self::PrizeWinner => '#00A0A1',
            self::Finisher => '#76D8CC',
            self::SuperBreeder => '#5D339A',
            self::BreederOfWinners => '#815AC4',
            self::Foundation => '#B7B1F8',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SuperBreeder, self::BreederOfWinners, self::Foundation => 'breeding',
            default => 'racing',
        };
    }

    /**
     * Rough ranking used to sort "proven" birds; higher is more significant.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Olympiad => 10,
            self::SuperBreeder => 9,
            self::AcePigeon, self::OverallWinner => 8,
            self::FinalRaceWinner => 7,
            self::RaceWinner, self::BreederOfWinners => 6,
            self::HotSpotWinner => 5,
            self::Superset => 4,
            self::Foundation => 3,
            self::PrizeWinner => 2,
            self::Finisher => 1,
        };
    }

    /**
     * @return list<array{value: string, label: string, code: string, description: string, category: string, color: string, weight: int}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'code' => $type->code(),
            'description' => $type->description(),
            'category' => $type->category(),
            'color' => $type->color(),
            'weight' => $type->weight(),
        ], self::cases());
    }
}
