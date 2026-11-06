<?php

namespace App\Services;

use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class PedigreeService
{
    /**
     * Generations on the chart, counting the subject (1 + 2 + 4 + 8 + 16 = 31 birds).
     */
    public const GENERATIONS = 5;

    private const RELATIONS = [
        'bloodlines',
        'pedigreeAncestors',
        'merits',
    ];

    /**
     * Props for rendering a pedigree chart.
     *
     * Remarks are included because they are meant for the pedigree; notes are private and never included.
     * Public charts also omit internal pigeon IDs.
     */
    public function chart(Pigeon $pigeon, bool $public = false): array
    {
        $pedigree = $pigeon->pedigreeRecord();
        $shareUrl = route('pedigrees.shared', $pedigree->serial);

        return [
            'tree' => $this->tree($pigeon, $public),
            'meta' => [
                'serial' => $pedigree->serial,
                'is_public' => $pedigree->is_public,
                'visibility' => $pedigree->visibility(),
                'share_url' => $shareUrl,
                'qr_svg' => $this->qrSvg($shareUrl),
                ...$this->breeder($pigeon->user),
                'photo_url' => $pigeon->photo_url,
                'generated_at' => now()->toDateString(),
            ],
        ];
    }

    /**
     * Props for a sample chart of a youngster from $sire × $dam (Test Pairing): the pedigree it would have,
     * built from the parents' own pedigrees. Owner view only; it has no serial, share link or QR code.
     * Both birds must belong to $breeder.
     */
    public function sampleChart(User $breeder, Pigeon $sire, Pigeon $dam): array
    {
        $tree = [
            'id' => null,
            'external' => false,
            'anchor_id' => null,
            'path' => null,
            'name' => 'Youngster',
            'ring_number' => null,
            'color' => null,
            'gender' => null,
            'hatch_date' => null,
            'bloodline' => null,
            'remarks' => null,
            'merits' => [],
            'sire' => $this->tree($sire, generations: self::GENERATIONS - 1),
            'dam' => $this->tree($dam, generations: self::GENERATIONS - 1),
        ];

        // The youngster's bloodline: the parents' shared line, or both
        $lines = array_values(array_unique(array_filter([$tree['sire']['bloodline'], $tree['dam']['bloodline']])));
        $tree['bloodline'] = $lines ? implode(' × ', $lines) : null;

        return [
            'tree' => $tree,
            'meta' => [
                'serial' => 'SAMPLE',
                'is_public' => false,
                'visibility' => 'private',
                'share_url' => '',
                'qr_svg' => '',
                ...$this->breeder($breeder),
                'photo_url' => null,
                'generated_at' => now()->toDateString(),
            ],
        ];
    }

    /**
     * The breeder name and the contact details the breeder chose to show; never the account email.
     *
     * @return array{breeder: string, contact: array{phone: ?string, email: ?string, address: ?string}}
     */
    private function breeder(User $user): array
    {
        $profile = $user->breederProfile;

        return [
            'breeder' => $profile?->display_name ?: $user->name,
            'contact' => $profile?->pedigreeContact() ?? ['phone' => null, 'email' => null, 'address' => null],
        ];
    }

    /**
     * The pedigree of $subject: the bird and $generations - 1 generations of ancestors.
     */
    public function tree(Pigeon $subject, bool $public = false, int $generations = self::GENERATIONS): array
    {
        $subject->loadMissing(self::RELATIONS);
        $loaded = [$subject->id => $subject];
        $frontier = [$subject];

        // Load ancestors one generation per query rather than one query per bird
        for ($generation = 1; $generation < $generations && $frontier; $generation++) {
            $ids = collect($frontier)
                ->flatMap(fn (Pigeon $pigeon) => [$pigeon->sire_id, $pigeon->dam_id])
                ->filter(fn ($id) => $id && ! isset($loaded[$id]))
                ->unique()
                ->values();

            $frontier = $ids->isEmpty() ? [] : Pigeon::with(self::RELATIONS)
                ->where('user_id', $subject->user_id)
                ->whereIn('id', $ids)
                ->get()
                ->all();

            foreach ($frontier as $pigeon) {
                $loaded[$pigeon->id] = $pigeon;
            }
        }

        return $this->node($subject, $loaded, $generations - 1, $public);
    }

    /**
     * Render a QR code as an inline SVG string.
     */
    public function qrSvg(string $content): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(192, 0), new SvgImageBackEnd)))->writeString($content);

        // Strip the XML declaration so the SVG can be inlined
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * @param  array<int, Pigeon>  $loaded
     */
    private function node(Pigeon $pigeon, array $loaded, int $remaining, bool $public): array
    {
        return [
            'id' => $public ? null : $pigeon->id,
            'external' => false,
            'anchor_id' => null,
            'path' => null,
            'name' => $pigeon->name,
            'ring_number' => $pigeon->ring_number,
            'color' => $pigeon->color,
            'gender' => $pigeon->gender,
            'hatch_date' => $pigeon->hatch_date?->format('Y-m-d'),
            'bloodline' => $pigeon->bloodlines->firstWhere('pivot.is_primary', true)?->name ?? $pigeon->bloodline,
            'remarks' => $pigeon->remarks,
            'merits' => $this->merits($pigeon->merits->map->summary()->values()->all(), $public),
            'sire' => $remaining > 0 ? $this->parent($pigeon, 'sire', $loaded, $remaining - 1, $public) : null,
            'dam' => $remaining > 0 ? $this->parent($pigeon, 'dam', $loaded, $remaining - 1, $public) : null,
        ];
    }

    /**
     * A linked parent becomes a full node; otherwise fall back to the external parent details stored on the child.
     *
     * @param  'sire'|'dam'  $side
     * @param  array<int, Pigeon>  $loaded
     */
    private function parent(Pigeon $child, string $side, array $loaded, int $remaining, bool $public): ?array
    {
        $parentId = $child->{"{$side}_id"};

        if ($parentId && isset($loaded[$parentId])) {
            return $this->node($loaded[$parentId], $loaded, $remaining, $public);
        }

        $name = $child->{"{$side}_name"};
        $ringNumber = $child->{"{$side}_ring_number"};
        $color = $child->{"{$side}_color"};
        $remarks = $child->{"{$side}_notes"};

        if (! $name && ! $ringNumber && ! $color && ! $remarks) {
            return null;
        }

        return $this->externalNode($child, $side === 'sire' ? 'S' : 'D', [
            'name' => $name,
            'ring_number' => $ringNumber,
            'color' => $color,
            'bloodline' => $child->{"{$side}_bloodline"},
            'remarks' => $remarks,
            'merits' => PigeonMerit::summarizeList($child->{"{$side}_merits"}),
        ], $remaining, $public);
    }

    /**
     * An ancestor recorded on $anchor (the nearest loft bird) at $path, e.g. "SD" for the anchor's sire's dam.
     */
    private function ancestor(Pigeon $anchor, string $path, int $remaining, bool $public): ?array
    {
        $ancestor = $anchor->pedigreeAncestors->firstWhere('path', $path);

        if (! $ancestor) {
            return null;
        }

        return $this->externalNode($anchor, $path, [
            'name' => $ancestor->name,
            'ring_number' => $ancestor->ring_number,
            'color' => $ancestor->color,
            'bloodline' => $ancestor->bloodline,
            'remarks' => $ancestor->remarks,
            'merits' => PigeonMerit::summarizeList($ancestor->merits),
        ], $remaining, $public);
    }

    /**
     * Owner charts include where an external bird is stored (anchor_id + path) so the pedigree editor can update it.
     */
    private function externalNode(Pigeon $anchor, string $path, array $details, int $remaining, bool $public): array
    {
        return [
            'id' => null,
            'external' => true,
            'anchor_id' => $public ? null : $anchor->id,
            'path' => $public ? null : $path,
            ...$details,
            'gender' => str_ends_with($path, 'S') ? 'male' : 'female',
            'hatch_date' => null,
            'sire' => $remaining > 0 ? $this->ancestor($anchor, $path.'S', $remaining - 1, $public) : null,
            'dam' => $remaining > 0 ? $this->ancestor($anchor, $path.'D', $remaining - 1, $public) : null,
        ];
    }

    /**
     * Public charts leave out the internal merit IDs.
     */
    private function merits(array $merits, bool $public): array
    {
        return $public ? array_map(fn (array $merit) => [...$merit, 'id' => null], $merits) : $merits;
    }
}
