<?php

namespace App\Services\Pedigree;

use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\PedigreeService;
use Illuminate\Support\Collection;

/**
 * What a pedigree page shows below the chart: photos and certificates of the bird and of its parents from the loft,
 * and the related loft birds the owner features as notable relatives.
 *
 * Each image appears once. A parent's photos of its own relatives are renamed for the bird: the sire's brother is
 * its uncle on the father's side, the sire's dam its grandmother, and so on. Birds on the chart are never notable
 * relatives, and each notable relative is listed once, under its closest relation.
 */
class PedigreeShowcase
{
    private const SIDE_WORD = ['S' => 'sire', 'D' => 'dam'];

    /**
     * @return array{
     *     photos: list<string>,
     *     relative_photos: list<array{url: string, relation: string}>,
     *     documents: list<array{url: string, of: string}>,
     *     notable_relatives: list<array<string, mixed>>,
     * }
     */
    public function for(Pigeon $pigeon, bool $public = false): array
    {
        $shown = $pigeon->publicPedigreeImages();
        $relativePhotos = $shown['relatives'];
        $documents = array_map(fn (string $url) => ['url' => $url, 'of' => ''], $shown['documents']);

        foreach ($this->parents($pigeon) as $side => $parent) {
            // The parent's main photo is on its chart card already; the rest of its photos go with it
            foreach (array_slice($parent->allPhotos(), 1) as $url) {
                $relativePhotos[] = ['url' => $url, 'relation' => $side];
            }

            $parentShown = $parent->publicPedigreeImages();

            foreach ($parentShown['relatives'] as $photo) {
                if ($relation = $this->throughParent($side, $photo['relation'])) {
                    $relativePhotos[] = ['url' => $photo['url'], 'relation' => $relation];
                }
            }

            foreach ($parentShown['documents'] as $url) {
                $documents[] = ['url' => $url, 'of' => $side];
            }
        }

        return [
            'photos' => $pigeon->allPhotos(),
            'relative_photos' => $this->uniqueUrls($relativePhotos),
            'documents' => $this->uniqueUrls($documents),
            'notable_relatives' => $this->notableRelatives($pigeon, $public),
        ];
    }

    /**
     * Loft birds related to $pigeon that are not on its chart and have merits or a photo: its brothers and sisters,
     * its sons and daughters, and its parents' brothers and sisters. Each bird is listed once, under its closest
     * relation, best merits first within each.
     *
     * @return Collection<int, array{pigeon: Pigeon, relation: string}>
     */
    public function candidates(Pigeon $pigeon): Collection
    {
        $exclude = array_flip([$pigeon->id, ...$this->chartIds($pigeon)]);
        $found = collect();

        $add = function (Collection $birds, callable $relation) use (&$exclude, $found) {
            $birds
                ->reject(fn (Pigeon $bird) => isset($exclude[$bird->id]))
                ->sortByDesc(fn (Pigeon $bird) => $bird->merits->sum(fn (PigeonMerit $merit) => $merit->type->weight()))
                ->each(function (Pigeon $bird) use (&$exclude, $found, $relation) {
                    $exclude[$bird->id] = true;
                    $found->push(['pigeon' => $bird, 'relation' => $relation($bird)]);
                });
        };

        // Brothers and sisters: full siblings first, then half-siblings
        $siblings = $this->sharingParents($pigeon->user_id, $pigeon->sire_id, $pigeon->dam_id);
        $full = fn (Pigeon $bird) => $pigeon->sire_id && $pigeon->dam_id
            && $bird->sire_id === $pigeon->sire_id && $bird->dam_id === $pigeon->dam_id;
        $add($siblings->filter($full), fn () => 'sibling');
        $add($siblings->reject($full), fn (Pigeon $bird) => $pigeon->sire_id && $bird->sire_id === $pigeon->sire_id
            ? 'half_sibling_sire'
            : 'half_sibling_dam');

        // Sons and daughters
        $add($this->related($pigeon->user_id)
            ->where(fn ($query) => $query->where('sire_id', $pigeon->id)->orWhere('dam_id', $pigeon->id))
            ->get(), fn () => 'offspring');

        // Aunts and uncles: the parents' brothers and sisters
        foreach ($this->parents($pigeon) as $side => $parent) {
            $add(
                $this->sharingParents($pigeon->user_id, $parent->sire_id, $parent->dam_id),
                fn () => 'aunt_uncle_'.self::SIDE_WORD[$side],
            );
        }

        return $found->values();
    }

    /**
     * The notable relatives the owner chose, still related and off the chart, as shown on the pedigree.
     *
     * @return list<array<string, mixed>>
     */
    private function notableRelatives(Pigeon $pigeon, bool $public): array
    {
        $featured = $pigeon->featured_relatives ?? [];

        if (! $featured) {
            return [];
        }

        return $this->candidates($pigeon)
            ->filter(fn (array $candidate) => in_array($candidate['pigeon']->id, $featured, true))
            ->map(fn (array $candidate) => $this->notable($candidate['pigeon'], $candidate['relation'], $public))
            ->values()
            ->all();
    }

    /**
     * A notable relative as shown below a pedigree. Public pedigrees leave out internal ids; notes never appear.
     *
     * @return array<string, mixed>
     */
    public function notable(Pigeon $bird, string $relation, bool $public): array
    {
        return [
            'id' => $public ? null : $bird->id,
            'name' => $bird->name,
            'ring_number' => $bird->ring_number,
            'gender' => $bird->gender,
            'color' => $bird->color,
            'relation' => $relation,
            'photo_url' => $bird->photo_url,
            'merits' => $bird->merits
                ->map(fn (PigeonMerit $merit) => [...$merit->summary(), 'id' => $public ? null : $merit->id])
                ->values()
                ->all(),
        ];
    }

    /**
     * How a relative of the parent on $side is related to the bird, or null when it doesn't fit the pedigree.
     */
    private function throughParent(string $side, string $relation): ?string
    {
        // The parent's ancestors are the bird's, one generation further back
        if (preg_match('/^[SD]+$/', $relation)) {
            return strlen($relation) < 4 ? $side.$relation : null;
        }

        $word = self::SIDE_WORD[$side];

        return match ($relation) {
            'sibling', 'half_sibling_sire', 'half_sibling_dam' => "aunt_uncle_{$word}",
            // Another son or daughter of this parent shares it with the bird
            'offspring' => "half_sibling_{$word}",
            default => 'relative',
        };
    }

    /**
     * The bird's parents from the loft, keyed by side ('S' sire, 'D' dam).
     *
     * @return array<string, Pigeon>
     */
    private function parents(Pigeon $pigeon): array
    {
        $parents = Pigeon::query()
            ->where('user_id', $pigeon->user_id)
            ->whereIn('id', array_filter([$pigeon->sire_id, $pigeon->dam_id]))
            ->get()
            ->keyBy('id');

        return array_filter([
            'S' => $pigeon->sire_id ? $parents->get($pigeon->sire_id) : null,
            'D' => $pigeon->dam_id ? $parents->get($pigeon->dam_id) : null,
        ]);
    }

    /**
     * Loft birds with the given sire or dam (either), worth featuring.
     *
     * @return Collection<int, Pigeon>
     */
    private function sharingParents(int $userId, ?int $sireId, ?int $damId): Collection
    {
        if (! $sireId && ! $damId) {
            return collect();
        }

        return $this->related($userId)
            ->where(fn ($query) => $query
                ->when($sireId, fn ($query) => $query->orWhere('sire_id', $sireId))
                ->when($damId, fn ($query) => $query->orWhere('dam_id', $damId)))
            ->get();
    }

    /**
     * The user's birds worth featuring: with merits or a photo.
     */
    private function related(int $userId)
    {
        return Pigeon::query()
            ->where('user_id', $userId)
            ->where(fn ($query) => $query->whereNotNull('photo_url')->orWhereHas('merits'))
            ->with([...Pigeon::SEARCH_RELATIONS, 'merits'])
            ->orderBy('ring_number');
    }

    /**
     * Ids of the loft birds on the bird's chart (its ancestors from the loft, as far back as the chart goes).
     *
     * @return list<int>
     */
    private function chartIds(Pigeon $pigeon): array
    {
        $ids = [];
        $frontier = [$pigeon->sire_id, $pigeon->dam_id];

        for ($generation = 1; $generation < PedigreeService::GENERATIONS; $generation++) {
            $frontier = array_values(array_unique(array_filter($frontier)));

            if (! $frontier) {
                break;
            }

            $birds = Pigeon::where('user_id', $pigeon->user_id)->whereIn('id', $frontier)->get(['id', 'sire_id', 'dam_id']);
            array_push($ids, ...$birds->pluck('id')->all());
            $frontier = $birds->flatMap(fn (Pigeon $bird) => [$bird->sire_id, $bird->dam_id])->all();
        }

        return $ids;
    }

    /**
     * @template T of array{url: string}
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function uniqueUrls(array $items): array
    {
        return array_values(collect($items)->unique('url')->all());
    }
}
