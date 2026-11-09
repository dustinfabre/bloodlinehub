<?php

namespace App\Services\LoftCheck;

use App\Models\Location;
use App\Models\Pigeon;
use App\Models\User;
use App\Support\RingSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The group of birds a loft check counts: the birds in the loft at one location (or all locations), in one ring
 * series (or all), with some of the in-loft statuses. Birds away at an OLR are never counted at home.
 */
final class LoftCheckScope
{
    public const ALL = 'all';

    // No location, or no ring series (no ring number, or one that doesn't end in a number)
    public const NONE = 'none';

    // The series of rings that are only digits
    public const DIGITS_ONLY = 'digits';

    /** @var Collection<int, Pigeon>|null */
    private ?Collection $loaded = null;

    /**
     * @param  list<string>  $statuses
     */
    private function __construct(
        public readonly User $user,
        public readonly string $location,
        public readonly ?Location $locationModel,
        public readonly string $series,
        public readonly array $statuses,
    ) {}

    /**
     * Read the scope from request input (location, series, status[]). Anything unknown falls back to the default
     * (all locations, all series, every in-loft status), unless $strict, where an unknown location is an error.
     */
    public static function fromInput(User $user, array $input, bool $strict = false): self
    {
        $location = is_scalar($input['location'] ?? null) ? (string) $input['location'] : self::ALL;
        $locationModel = null;

        if (! in_array($location, [self::ALL, self::NONE], true)) {
            $locationModel = ctype_digit($location)
                ? Location::where('user_id', $user->id)->find((int) $location)
                : null;

            if (! $locationModel && $strict) {
                throw ValidationException::withMessages(['location' => 'That location is not one of yours.']);
            }

            $location = $locationModel ? (string) $locationModel->id : self::ALL;
        }

        // Written like RingSeries writes it, so "pha 2026" is the series PHA-2026
        $series = is_scalar($input['series'] ?? null)
            ? trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $input['series'])), '-')
            : '';
        $series = in_array(strtolower($series), ['', self::ALL, self::NONE, self::DIGITS_ONLY], true)
            ? (strtolower($series) ?: self::ALL)
            : $series;

        $statusInput = array_filter((array) ($input['status'] ?? []), 'is_string');
        // Kept in the standard order, so the same group always has the same scope
        $statuses = array_values(array_intersect(Pigeon::IN_LOFT_STATUSES, $statusInput)) ?: Pigeon::IN_LOFT_STATUSES;

        return new self($user, $location, $locationModel, $series, $statuses);
    }

    /**
     * The scope as stored on a loft check and sent back as query parameters to count the same group again.
     *
     * @return array{location: string, series: string, status: list<string>}
     */
    public function toArray(): array
    {
        return ['location' => $this->location, 'series' => $this->series, 'status' => $this->statuses];
    }

    // What is counted, in words: "Young bird loft · PHA-2026 · Racing"
    public function label(): string
    {
        $parts = [match ($this->location) {
            self::ALL => 'All locations',
            self::NONE => 'No location',
            default => $this->locationModel->name,
        }];

        if ($this->series !== self::ALL) {
            $parts[] = self::seriesLabel($this->series);
        }

        if ($this->statuses !== Pigeon::IN_LOFT_STATUSES) {
            $parts[] = implode(', ', array_map(fn (string $status) => Pigeon::STATUS_LABELS[$status], $this->statuses));
        }

        return implode(' · ', $parts);
    }

    public static function seriesLabel(string $series): string
    {
        return match ($series) {
            self::NONE => 'No ring series',
            self::DIGITS_ONLY => 'Numbers only',
            default => $series,
        };
    }

    /**
     * The birds to count: in the loft, with one of the chosen statuses, in the location and series.
     *
     * @return Collection<int, Pigeon> keyed by id, in ring order
     */
    public function birds(): Collection
    {
        return $this->inLocation()
            ->filter(fn (Pigeon $pigeon) => in_array($pigeon->status, $this->statuses, true))
            ->filter(fn (Pigeon $pigeon) => $this->inSeries($pigeon));
    }

    /**
     * Missing birds of the same location and series, so a bird that came back can be ticked too.
     *
     * @return Collection<int, Pigeon> keyed by id, in ring order
     */
    public function missingBirds(): Collection
    {
        return $this->inLocation()
            ->filter(fn (Pigeon $pigeon) => $pigeon->status === 'missing')
            ->filter(fn (Pigeon $pigeon) => $this->inSeries($pigeon));
    }

    /**
     * The ring series among the birds to count at this location (whatever series is chosen), largest first and
     * birds without a series last.
     *
     * @return list<array{value: string, label: string, count: int, from: ?string, to: ?string}>
     */
    public function seriesOptions(): array
    {
        return $this->inLocation()
            ->filter(fn (Pigeon $pigeon) => in_array($pigeon->status, $this->statuses, true))
            ->groupBy(fn (Pigeon $pigeon) => self::seriesOf($pigeon)['series'])
            ->map(function (Collection $birds, string $series) {
                $numbers = $birds->map(fn (Pigeon $pigeon) => self::seriesOf($pigeon)['number'])->filter()
                    ->sortBy(fn (string $number) => (int) $number)->values();

                return [
                    'value' => $series,
                    'label' => self::seriesLabel($series),
                    'count' => $birds->count(),
                    'from' => $numbers->first(),
                    'to' => $numbers->last(),
                ];
            })
            // Largest series first; birds without a series last
            ->sortBy([
                fn (array $a, array $b) => ($a['value'] === self::NONE) <=> ($b['value'] === self::NONE),
                ['count', 'desc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * A bird as shown on the check board: the standard search result details, plus its status, location, ring
     * series and number within the series.
     */
    public static function boardItem(Pigeon $pigeon): array
    {
        return [
            ...$pigeon->searchSummary(),
            'status' => $pigeon->status,
            'location' => $pigeon->location?->name,
            ...self::seriesOf($pigeon),
        ];
    }

    /**
     * @return array{series: string, number: ?string}
     */
    public static function seriesOf(Pigeon $pigeon): array
    {
        $parsed = RingSeries::parse($pigeon->ring_number);

        if (! $parsed) {
            return ['series' => self::NONE, 'number' => null];
        }

        return ['series' => $parsed['series'] === '' ? self::DIGITS_ONLY : $parsed['series'], 'number' => $parsed['number']];
    }

    private function inSeries(Pigeon $pigeon): bool
    {
        return $this->series === self::ALL || self::seriesOf($pigeon)['series'] === $this->series;
    }

    /**
     * The user's birds at the chosen location that are in the loft or missing, loaded once.
     *
     * @return Collection<int, Pigeon>
     */
    private function inLocation(): Collection
    {
        if ($this->loaded) {
            return $this->loaded;
        }

        $query = Pigeon::query()
            ->where('user_id', $this->user->id)
            ->whereIn('status', [...Pigeon::IN_LOFT_STATUSES, 'missing'])
            ->with([...Pigeon::SEARCH_RELATIONS, 'location:id,name']);

        match ($this->location) {
            // Birds away at an OLR are not at home to count
            self::ALL => $query->whereDoesntHave('location', fn (Builder $location) => $location->where('type', 'olr')),
            self::NONE => $query->whereNull('location_id'),
            default => $query->where('location_id', $this->locationModel->id),
        };

        return $this->loaded = $query->get([...Pigeon::SEARCH_COLUMNS, 'status', 'location_id'])
            ->sortBy([
                // Birds without a ring series go last
                fn (Pigeon $a, Pigeon $b) => (self::seriesOf($a)['series'] === self::NONE) <=> (self::seriesOf($b)['series'] === self::NONE),
                fn (Pigeon $a, Pigeon $b) => self::seriesOf($a)['series'] <=> self::seriesOf($b)['series'],
                fn (Pigeon $a, Pigeon $b) => (int) self::seriesOf($a)['number'] <=> (int) self::seriesOf($b)['number'],
                fn (Pigeon $a, Pigeon $b) => ($a->ring_number ?? $a->personal_number ?? '') <=> ($b->ring_number ?? $b->personal_number ?? ''),
            ])
            ->keyBy('id');
    }
}
