<?php

namespace App\Services;

use App\Models\ColorTag;
use App\Models\Pigeon;
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
        'colorTag:id,name,color',
        'sireColorTag:id,name,color',
        'damColorTag:id,name,color',
        'bloodlines',
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
                'share_url' => $shareUrl,
                'qr_svg' => $this->qrSvg($shareUrl),
                'breeder' => $pigeon->user->name,
                'photo_url' => $pigeon->photo_url,
                'generated_at' => now()->toDateString(),
            ],
        ];
    }

    public function tree(Pigeon $subject, bool $public = false): array
    {
        $subject->loadMissing(self::RELATIONS);
        $loaded = [$subject->id => $subject];
        $frontier = [$subject];

        // Load ancestors one generation per query rather than one query per bird
        for ($generation = 1; $generation < self::GENERATIONS && $frontier; $generation++) {
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

        return $this->node($subject, $loaded, self::GENERATIONS - 1, $public);
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
            'name' => $pigeon->name,
            'ring_number' => $pigeon->ring_number,
            'color' => $pigeon->color,
            'gender' => $pigeon->gender,
            'hatch_date' => $pigeon->hatch_date?->format('Y-m-d'),
            'bloodline' => $pigeon->bloodlines->firstWhere('pivot.is_primary', true)?->name ?? $pigeon->bloodline,
            'remarks' => $pigeon->remarks,
            'highlight' => $this->highlight($pigeon->colorTag),
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

        return [
            'id' => null,
            'external' => true,
            'name' => $name,
            'ring_number' => $ringNumber,
            'color' => $color,
            'gender' => $side === 'sire' ? 'male' : 'female',
            'hatch_date' => null,
            'bloodline' => null,
            'remarks' => $remarks,
            'highlight' => $this->highlight($child->{"{$side}ColorTag"}),
            'sire' => null,
            'dam' => null,
        ];
    }

    private function highlight(?ColorTag $tag): ?array
    {
        return $tag ? ['name' => $tag->name, 'color' => $tag->color] : null;
    }
}
