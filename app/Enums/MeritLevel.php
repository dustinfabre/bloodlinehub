<?php

namespace App\Enums;

/**
 * Where a merit was earned, so one merit type covers "1st Club" through "1st National".
 */
enum MeritLevel: string
{
    case Club = 'club';
    case Federation = 'federation';
    case Provincial = 'provincial';
    case National = 'national';
    case International = 'international';
    case Derby = 'derby';
    case Olr = 'olr';

    public function label(): string
    {
        return match ($this) {
            self::Club => 'Club',
            self::Federation => 'Federation',
            self::Provincial => 'Provincial / Regional',
            self::National => 'National',
            self::International => 'International',
            self::Derby => 'Derby',
            self::Olr => 'One Loft Race',
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::Club => 'Club',
            self::Federation => 'Fed',
            self::Provincial => 'Prov',
            self::National => 'Nat',
            self::International => 'Int',
            self::Derby => 'Derby',
            self::Olr => 'OLR',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $level) => ['value' => $level->value, 'label' => $level->label()], self::cases());
    }
}
