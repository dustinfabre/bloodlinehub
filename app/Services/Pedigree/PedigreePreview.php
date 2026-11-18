<?php

namespace App\Services\Pedigree;

use App\Models\DriveFile;
use App\Models\GoogleDriveConnection;
use App\Services\GoogleDrive\GoogleDrive;
use App\Services\GoogleDrive\GoogleDriveException;
use GdImage;
use Illuminate\Support\Facades\Cache;

/**
 * The link preview of a shared pedigree: the picture that social media and chat apps show when someone posts the
 * link (og:image). A 1200 × 630 card in the style of the pedigree sheet: the bird with its photo, then its parents
 * and grandparents with their merits.
 *
 * Drawn with GD at twice the size and scaled down, which smooths the curves GD draws without antialiasing. Text is
 * set in Instrument Sans (resources/fonts, SIL Open Font License), the sheet's font. Only public pedigrees get one
 * (PedigreePreviewController); drawn images are cached under version().
 */
class PedigreePreview
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    // Bump when the drawing changes: the version is in the og:image URL, so apps fetch the new picture
    private const LAYOUT = 1;

    private const SCALE = 2;

    private const CACHE_DAYS = 7;

    // The sheet's palette (PedigreeChart.vue)
    private const NAVY = '#14243a';

    private const GOLD = '#d4af62';

    private const GOLD_DEEP = '#a07d34';

    private const IVORY = '#f8f6f0';

    private const WHITE = '#ffffff';

    private const MUTED = '#64748b';

    private const FAINT = '#8390a2';

    private const SURFACE = '#f4f2ec';

    private const RULE = '#e6e2d8';

    private const LINE = '#c2c7d0';

    private const SIRE = '#3a6ea5';

    private const DAM = '#b4506b';

    private const FONTS = [
        'regular' => 'InstrumentSans-Regular.ttf',
        'semibold' => 'InstrumentSans-SemiBold.ttf',
        'bold' => 'InstrumentSans-Bold.ttf',
    ];

    private const MARGIN = 32;

    // Columns: the bird, its parents, its grandparents [x, width]
    private const COLUMNS = [[32, 330], [398, 356], [790, 378]];

    private const TREE_TOP = 140;

    private const TREE_HEIGHT = 426;

    private const FOOTER_TOP = 580;

    // Text and chip sizes on each generation's cards, and the space above the name and above the merits, in pixels
    private const SIZES = [
        0 => ['pad' => 20, 'badge' => 26, 'title' => 30, 'name' => 23, 'details' => 17, 'chip' => 14, 'pill' => 27, 'name_gap' => 6, 'chip_gap' => 12],
        1 => ['pad' => 16, 'badge' => 22, 'title' => 24, 'name' => 19, 'details' => 16, 'chip' => 13, 'pill' => 24, 'name_gap' => 3, 'chip_gap' => 8],
        2 => ['pad' => 14, 'badge' => 19, 'title' => 20, 'name' => 16, 'details' => 15, 'chip' => 12, 'pill' => 21, 'name_gap' => 2, 'chip_gap' => 5],
    ];

    // What a card shows, most first, until it fits: [lines of name, lines of color and bloodline, rows of merits]
    private const FITS = [
        0 => [[2, 2, 2], [2, 1, 2], [1, 1, 2], [1, 1, 1], [1, 0, 1], [1, 0, 0], [0, 0, 0]],
        1 => [[1, 2, 2], [1, 1, 2], [1, 1, 1], [1, 0, 1], [1, 0, 0], [0, 0, 0]],
        2 => [[1, 1, 1], [1, 0, 1], [1, 1, 0], [1, 0, 0], [0, 0, 0]],
    ];

    // The canvas, at SCALE times the final size, while drawing
    private ?GdImage $image = null;

    /** @var array<string, float> */
    private array $capHeights = [];

    public function __construct(private GoogleDrive $drive) {}

    /**
     * Whether this server can draw previews: GD with FreeType (text) and WebP (the birds' photos).
     */
    public static function available(): bool
    {
        return function_exists('imagettftext') && function_exists('imagecreatefromwebp');
    }

    /**
     * What the preview shows of a pedigree, from a public chart: PedigreeService::chart(), or tree() with credits().
     * Only the bird, its parents and its grandparents are read, so a three-generation tree gives the same card.
     *
     * @param  array<string, mixed>  $tree
     * @param  array{serial: string, breeder: string, role: string, bred_by: ?string}  $meta
     * @return array<string, mixed>
     */
    public function card(array $tree, array $meta): array
    {
        $sire = $tree['sire'] ?? null;
        $dam = $tree['dam'] ?? null;

        return [
            'serial' => $meta['serial'],
            'breeder' => $meta['breeder'],
            'role' => $meta['role'],
            'bred_by' => $meta['bred_by'] ?? null,
            'photo_url' => $tree['photo_url'] ?? null,
            'bird' => $this->bird($tree, 0, $tree['gender'] ?? null),
            'parents' => [$this->bird($sire, 1, 'male'), $this->bird($dam, 1, 'female')],
            'grandparents' => [
                $this->bird($sire['sire'] ?? null, 2, 'male'),
                $this->bird($sire['dam'] ?? null, 2, 'female'),
                $this->bird($dam['sire'] ?? null, 2, 'male'),
                $this->bird($dam['dam'] ?? null, 2, 'female'),
            ],
        ];
    }

    /**
     * Changes whenever the picture would: part of the og:image URL (apps cache previews by URL) and the cache key.
     *
     * @param  array<string, mixed>  $card
     */
    public function version(array $card): string
    {
        return substr(sha1(json_encode([self::LAYOUT, $card], JSON_THROW_ON_ERROR)), 0, 12);
    }

    /**
     * The words that go with the picture (og:title, og:description and the picture's alt text).
     *
     * @param  array<string, mixed>  $card
     * @return array{title: string, description: string, image_alt: string}
     */
    public function describe(array $card): array
    {
        $bird = $this->label($card['bird']) ?? $card['serial'];

        $credit = $card['role'] === 'owner' ? "Owned by {$card['breeder']}" : "Bred by {$card['breeder']}";
        if ($card['role'] === 'owner' && $card['bred_by']) {
            $credit .= " · Bred by {$card['bred_by']}";
        }

        [$sire, $dam] = array_map(fn (?array $parent) => $this->label($parent), $card['parents']);
        $parents = implode(' × ', array_filter([$sire ? "Sire {$sire}" : null, $dam ? "Dam {$dam}" : null]));

        $summary = implode(' · ', array_filter([$card['bird']['bloodline'] ?? null, $credit, $parents ?: null]));

        return [
            'title' => "Pedigree {$bird}",
            'description' => "{$summary}. Tap any bird to see its merits, photos and bloodline.",
            'image_alt' => "Pedigree of {$bird}: the bird, its parents and its grandparents",
        ];
    }

    /**
     * The preview as a PNG, and whether it is complete: false when the bird's photo couldn't be fetched just now,
     * in which case it isn't cached, so the next request tries again.
     *
     * @param  array<string, mixed>  $card
     * @return array{0: string, 1: bool}
     */
    public function png(array $card): array
    {
        // One entry per pedigree, replaced when the pedigree changes, so old versions don't pile up in the cache
        $key = "pedigree-preview:{$card['serial']}";
        $version = $this->version($card);
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['version'] ?? null) === $version) {
            $png = base64_decode((string) $cached['png'], true);

            if (is_string($png)) {
                return [$png, true];
            }
        }

        [$png, $complete] = $this->render($card);

        if ($complete) {
            // Base64: the database cache store keeps text
            Cache::put($key, ['version' => $version, 'png' => base64_encode($png)], now()->addDays(self::CACHE_DAYS));
        }

        return [$png, $complete];
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @return array<string, mixed>|null
     */
    private function bird(?array $node, int $depth, ?string $gender): ?array
    {
        if (! $node) {
            return null;
        }

        // Most significant first, then the most recent (as on the chart's cards)
        $merits = collect($node['merits'] ?? [])
            ->sortBy([['weight', 'desc'], ['year', 'desc']])
            ->map(fn (array $merit) => [
                // The chart's chip text: "ACE Nat '24" up front, "ACE '24" further back
                'text' => $depth <= 1
                    ? $merit['short']
                    : trim($merit['code'].($merit['year'] ? " '".substr((string) $merit['year'], -2) : '')),
                'color' => $merit['color'],
            ])
            ->values()
            ->all();

        return [
            'ring_number' => $node['ring_number'] ?? null,
            'name' => $node['name'] ?? null,
            'gender' => $gender,
            'color' => $node['color'] ?? null,
            'bloodline' => $node['bloodline'] ?? null,
            'merits' => $merits,
        ];
    }

    /**
     * "PH-2025-1234 “Blue Thunder”", or whichever of the two is known.
     *
     * @param  array<string, mixed>|null  $bird
     */
    private function label(?array $bird): ?string
    {
        $ring = $bird['ring_number'] ?? null;
        $name = $bird['name'] ?? null;

        return match (true) {
            filled($ring) && filled($name) && $name !== $ring => "{$ring} “{$name}”",
            filled($ring) => $ring,
            filled($name) => $name,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array{0: string, 1: bool}
     */
    private function render(array $card): array
    {
        $this->image = imagecreatetruecolor(self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);
        imagealphablending($this->image, true);
        $this->rect(0, 0, self::WIDTH, self::HEIGHT, self::WHITE);

        [$photo, $complete] = $this->photo($card['photo_url']);

        $this->band($card);
        $this->generations(array_filter($card['parents']) ? 3 : 2);
        $this->tree($card, $photo);
        $this->footer($card);

        $final = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled(
            $final, $this->image, 0, 0, 0, 0,
            self::WIDTH, self::HEIGHT, self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE,
        );
        // Let go of the canvas (12 MB) as soon as it is copied
        $this->image = null;

        ob_start();
        imagepng($final, null, 6);

        return [(string) ob_get_clean(), $complete];
    }

    /**
     * The bird's photo from its owner's Google Drive, and whether that is final: a bird without a photo is, Drive
     * failing for a moment is not.
     *
     * @return array{0: ?GdImage, 1: bool}
     */
    private function photo(?string $url): array
    {
        $id = DriveFile::idFromUrl($url);
        $file = $id ? DriveFile::find($id) : null;
        $connection = $file ? GoogleDriveConnection::where('user_id', $file->user_id)->first() : null;

        if (! $file || ! $connection) {
            return [null, true];
        }

        try {
            $contents = $this->drive->download($connection, $file->drive_file_id);
        } catch (GoogleDriveException) {
            return [null, false];
        }

        $photo = $contents ? @imagecreatefromstring($contents) : false;

        return [$photo ?: null, true];
    }

    /**
     * The navy band across the top, as on the sheet: logo, "Five-generation Pedigree", the loft and the serial.
     *
     * @param  array<string, mixed>  $card
     */
    private function band(array $card): void
    {
        $this->rect(0, 0, self::WIDTH, 88, self::NAVY);
        $this->rect(0, 88, self::WIDTH, 4, self::GOLD);

        $x = self::MARGIN;
        $logo = $this->asset('wordmark.png');
        $width = imagesx($logo) / self::SCALE;
        imagecopy($this->image, $logo, $this->px($x), $this->px(21), 0, 0, imagesx($logo), imagesy($logo));
        $x += $width + 22;
        $this->rect($x, 26, 1, 36, $this->mix(self::NAVY, self::IVORY, 0.18));
        $x += 22;

        $this->text('FIVE-GENERATION', $x, 40, 11, 'semibold', self::GOLD, 0.26);
        $this->text('Pedigree', $x, 70, 29, 'semibold', self::IVORY);

        // Laid out from the right edge: serial last
        $right = self::WIDTH - self::MARGIN;
        $facts = [
            [$card['role'] === 'owner' ? 'OWNER' : 'BREEDER', $this->fit($card['breeder'], 17, 'semibold', 330)],
            ['PEDIGREE NO.', $card['serial']],
        ];

        foreach (array_reverse($facts) as [$label, $value]) {
            $width = max($this->width($label, 10, 'semibold', 0.2), $this->width($value, 17, 'semibold'));
            $x = $right - $width;
            $this->text($label, $x, 40, 10, 'semibold', self::GOLD, 0.2);
            $this->text($value, $x, 66, 17, 'semibold', self::IVORY);
            $right = $x - 40;
        }
    }

    /**
     * "01 Bird  02 Parents  03 Grandparents" above the columns, as on the sheet (no grandparents column when neither
     * parent is known).
     */
    private function generations(int $columns): void
    {
        foreach (array_slice(['Bird', 'Parents', 'Grandparents'], 0, $columns) as $index => $label) {
            $x = self::COLUMNS[$index][0];
            $x += $this->text(sprintf('%02d', $index + 1), $x, 124, 12, 'bold', self::GOLD_DEEP, 0.16) + 7;
            $this->text(strtoupper($label), $x, 124, 12, 'semibold', self::MUTED, 0.16);
        }
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function tree(array $card, ?GdImage $photo): void
    {
        [[$x0, $w0], [$x1, $w1], [$x2, $w2]] = self::COLUMNS;
        $top = self::TREE_TOP;
        $height = self::TREE_HEIGHT;

        $parentY = [$top + $height / 4, $top + $height * 3 / 4];
        $grandY = [$top + $height / 8, $top + $height * 3 / 8, $top + $height * 5 / 8, $top + $height * 7 / 8];

        // Connectors first, the cards on top: as on the sheet, a known bird always gets both parent slots
        $this->fork($x0 + $w0, $top + $height / 2, $x1, $parentY);
        foreach ($card['parents'] as $index => $parent) {
            if ($parent) {
                $this->fork($x1 + $w1, $parentY[$index], $x2, array_slice($grandY, $index * 2, 2));
            }
        }

        $this->subject($card['bird'], $photo, $x0, $top, $w0, $height);

        foreach ($card['parents'] as $index => $parent) {
            $this->ancestor($parent, 1, $index === 0 ? 'male' : 'female', $x1, $parentY[$index] - 88, $w1, 176);

            if ($parent) {
                foreach ([0, 1] as $offset) {
                    $slot = $index * 2 + $offset;
                    $this->ancestor(
                        $card['grandparents'][$slot], 2, $offset === 0 ? 'male' : 'female',
                        $x2, $grandY[$slot] - 48, $w2, 96,
                    );
                }
            }
        }
    }

    /**
     * The bird itself: a navy card with its photo on top.
     *
     * @param  array<string, mixed>  $bird
     */
    private function subject(array $bird, ?GdImage $photo, float $x, float $y, float $width, float $height): void
    {
        $this->cardBackground($x, $y, $width, $height, 16, self::NAVY, $bird['merits'][0]['color'] ?? null, 5);

        $pad = self::SIZES[0]['pad'];
        $contentWidth = $width - $pad * 2;

        if ($photo) {
            $photoHeight = 212;
            $this->photoInto($photo, $x + 12, $y + 12, $width - 24, $photoHeight, 10, self::NAVY);
            $top = $y + 12 + $photoHeight + 16;
            $lines = $this->lines($bird, 0, $contentWidth, $y + $height - 18 - $top);
            $this->drawLines($lines, $x + $pad, $top, 0);

            return;
        }

        // No photo: the BloodlineHub crest above the bird's details
        $logo = $this->asset('crest.png');
        $crest = imagesy($logo) / self::SCALE;
        $lines = $this->lines($bird, 0, $contentWidth, $height - $pad * 2 - $crest - 24);
        $top = $y + ($height - $crest - 24 - $this->linesHeight($lines)) / 2;
        imagecopy($this->image, $logo, $this->px($x + $pad - 4), $this->px($top), 0, 0, imagesx($logo), imagesy($logo));

        $this->drawLines($lines, $x + $pad, $top + $crest + 24, 0);
    }

    /**
     * A parent or grandparent; an unknown one gets an empty dashed-style slot, as on the sheet.
     *
     * @param  array<string, mixed>|null  $bird
     */
    private function ancestor(?array $bird, int $depth, string $gender, float $x, float $y, float $width, float $height): void
    {
        $size = self::SIZES[$depth];

        if (! $bird) {
            $this->roundedRect($x, $y, $width, $height, 12, '#ddd8cc');
            $this->roundedRect($x + 1.5, $y + 1.5, $width - 3, $height - 3, 10.5, self::WHITE);
            $this->sexBadge($x + $size['pad'] + $size['badge'] / 2, $y + $height / 2, $size['badge'], $gender, false);
            $this->text(
                'Unknown', $x + $size['pad'] + $size['badge'] + 9,
                $y + ($height + $this->cap($size['details'], 'semibold')) / 2, $size['details'], 'semibold', self::FAINT,
            );

            return;
        }

        $accent = $bird['merits'][0]['color'] ?? null;
        $this->cardBackground($x, $y, $width, $height, 12, self::SURFACE, $accent, 4);

        $lines = $this->lines($bird, $depth, $width - $size['pad'] * 2, $height - 20);
        $this->drawLines($lines, $x + $size['pad'], $y + ($height - $this->linesHeight($lines)) / 2, $depth);
    }

    /**
     * A card, with a stripe down its left side in the color of the bird's most significant merit.
     */
    private function cardBackground(float $x, float $y, float $width, float $height, float $radius, string $fill, ?string $accent, float $stripe): void
    {
        if ($accent) {
            $this->roundedRect($x, $y, $width, $height, $radius, $accent);
            $this->roundedRect($x + $stripe, $y, $width - $stripe, $height, $radius, $fill);

            return;
        }

        $this->roundedRect($x, $y, $width, $height, $radius, $fill);
    }

    /**
     * A bird's lines, as many as fit in $available: ring number (or name), name, color and bloodline, merit chips.
     *
     * @param  array<string, mixed>  $bird
     * @return list<array<string, mixed>>
     */
    private function lines(array $bird, int $depth, float $width, float $available): array
    {
        foreach (self::FITS[$depth] as [$nameLines, $detailLines, $chipRows]) {
            $lines = $this->layout($bird, $depth, $width, $nameLines, $detailLines, $chipRows);

            if ($this->linesHeight($lines) <= $available) {
                return $lines;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $bird
     * @return list<array<string, mixed>>
     */
    private function layout(array $bird, int $depth, float $width, int $nameLines, int $detailLines, int $chipRows): array
    {
        $size = self::SIZES[$depth];
        $dark = $depth === 0;
        $ring = filled($bird['ring_number']) ? $bird['ring_number'] : null;
        $name = filled($bird['name']) && $bird['name'] !== $ring ? $bird['name'] : null;

        // Line 1: sex and ring number (or the name when there is no ring number)
        $titleWidth = $width - $size['badge'] - 9;
        $titlePx = $size['title'];
        $title = $ring ?? $name ?? 'Unknown';
        while ($titlePx > $size['title'] * 0.8 && $this->width($title, $titlePx, 'bold') > $titleWidth) {
            $titlePx--;
        }

        $lines = [[
            'type' => 'title',
            'text' => $this->fit($title, $titlePx, 'bold', $titleWidth),
            'px' => $titlePx,
            'gender' => $bird['gender'],
            'badge' => $size['badge'],
            'color' => $dark ? self::GOLD : self::NAVY,
            'height' => round($size['title'] * 1.25),
            'gap' => 0,
        ]];

        if ($ring && $name && $nameLines) {
            foreach ($this->wrap($name, $size['name'], 'semibold', $width, $nameLines) as $index => $text) {
                $lines[] = [
                    'type' => 'text', 'text' => $text, 'px' => $size['name'], 'weight' => 'semibold',
                    'color' => $dark ? self::WHITE : self::NAVY,
                    'height' => round($size['name'] * 1.3), 'gap' => $index ? 0 : $size['name_gap'],
                ];
            }
        }

        $details = implode(' · ', array_filter([$bird['color'], $bird['bloodline']], 'filled'));
        if ($details !== '' && $detailLines) {
            foreach ($this->wrap($details, $size['details'], 'regular', $width, $detailLines) as $index => $text) {
                $lines[] = [
                    'type' => 'text', 'text' => $text, 'px' => $size['details'], 'weight' => 'regular',
                    'color' => $dark ? $this->mix(self::NAVY, self::IVORY, 0.72) : self::MUTED,
                    'height' => round($size['details'] * 1.35), 'gap' => $index ? 0 : 2,
                ];
            }
        }

        if ($bird['merits'] && $chipRows) {
            [$rows, $more] = $this->chipRows($bird['merits'], $size['chip'], $size['pill'], $width, $chipRows);
            $lines[] = [
                'type' => 'chips', 'rows' => $rows, 'more' => $more, 'px' => $size['chip'], 'pill' => $size['pill'],
                'height' => count($rows) * $size['pill'] + (count($rows) - 1) * 6, 'gap' => $size['chip_gap'],
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function linesHeight(array $lines): float
    {
        return array_sum(array_map(fn (array $line) => $line['height'] + $line['gap'], $lines));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function drawLines(array $lines, float $x, float $y, int $depth): void
    {
        $dark = $depth === 0;

        foreach ($lines as $line) {
            $y += $line['gap'];

            if ($line['type'] === 'title') {
                $this->sexBadge($x + $line['badge'] / 2, $y + $line['height'] / 2, $line['badge'], $line['gender'], $dark);
                $this->text(
                    $line['text'], $x + $line['badge'] + 9, $this->baseline($y, $line['height'], $line['px'], 'bold'),
                    $line['px'], 'bold', $line['color'],
                );
            } elseif ($line['type'] === 'text') {
                $this->text(
                    $line['text'], $x, $this->baseline($y, $line['height'], $line['px'], $line['weight']),
                    $line['px'], $line['weight'], $line['color'],
                );
            } else {
                $this->chips($line, $x, $y, $dark);
            }

            $y += $line['height'];
        }
    }

    /**
     * Merit chips in up to $rows rows; the ones that don't fit are counted as "+N" at the end.
     *
     * @param  list<array{text: string, color: string}>  $merits
     * @return array{0: list<list<array{text: string, color: string, width: float}>>, 1: int}
     */
    private function chipRows(array $merits, float $px, float $pill, float $width, int $rows): array
    {
        $gap = 6;
        $padding = round($pill * 0.36);
        $result = [[]];
        $used = 0.0;

        foreach ($merits as $merit) {
            $chip = [...$merit, 'width' => $this->width($merit['text'], $px, 'bold') + $padding * 2];
            $row = count($result) - 1;

            if ($result[$row] && $used + $gap + $chip['width'] > $width) {
                if (count($result) === $rows) {
                    break;
                }
                $result[] = [];
                $row++;
                $used = 0.0;
            }

            if ($chip['width'] > $width) {
                break;
            }

            $used += ($result[$row] ? $gap : 0) + $chip['width'];
            $result[$row][] = $chip;
        }

        $placed = array_sum(array_map('count', $result));
        $more = count($merits) - $placed;
        $last = count($result) - 1;

        // Make room for "+N" on the last row
        while ($more && count($result[$last]) > 1) {
            $rowWidth = array_sum(array_column($result[$last], 'width')) + (count($result[$last]) - 1) * $gap;
            if ($rowWidth + $gap + $this->width("+{$more}", $px, 'bold') <= $width) {
                break;
            }
            array_pop($result[$last]);
            $more++;
        }

        return [array_values(array_filter($result)), $more];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function chips(array $line, float $x, float $y, bool $dark): void
    {
        $gap = 6;
        $pill = $line['pill'];
        $padding = round($pill * 0.36);
        $cursor = $x;

        foreach ($line['rows'] as $index => $row) {
            $cursor = $x;
            $top = $y + $index * ($pill + $gap);

            foreach ($row as $chip) {
                if ($dark) {
                    // Dark merit colors would melt into the navy card, so its chips get a faint light ring
                    $this->roundedRect($cursor - 1, $top - 1, $chip['width'] + 2, $pill + 2, $pill / 2 + 1, $this->mix(self::NAVY, self::IVORY, 0.28));
                }
                $this->roundedRect($cursor, $top, $chip['width'], $pill, $pill / 2, $chip['color']);
                $this->text(
                    $chip['text'], $cursor + $padding, $this->baseline($top, $pill, $line['px'], 'bold'),
                    $line['px'], 'bold', $this->chipTextColor($chip['color']),
                );
                $cursor += $chip['width'] + $gap;
            }
        }

        if ($line['more']) {
            $top = $y + (count($line['rows']) - 1) * ($pill + $gap);
            $this->text(
                "+{$line['more']}", $cursor, $this->baseline($top, $pill, $line['px'], 'bold'), $line['px'], 'bold',
                $dark ? $this->mix(self::NAVY, self::IVORY, 0.72) : self::MUTED,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function footer(array $card): void
    {
        $top = self::FOOTER_TOP;
        $this->rect(0, $top, self::WIDTH, self::HEIGHT - $top, self::SURFACE);
        $this->rect(0, $top, self::WIDTH, 1, self::RULE);

        $baseline = $this->baseline($top, self::HEIGHT - $top, 16, 'semibold');
        $link = preg_replace('#^https?://#', '', route('pedigrees.shared', $card['serial']));
        $linkWidth = $this->width($link, 15, 'semibold');
        $this->text($link, self::WIDTH - self::MARGIN - $linkWidth, $baseline, 15, 'semibold', self::MUTED);

        $this->text(
            $this->fit('All 5 generations online · Tap any bird for its merits and photos', 16, 'semibold', self::WIDTH - self::MARGIN * 2 - $linkWidth - 32),
            self::MARGIN, $baseline, 16, 'semibold', self::NAVY,
        );
    }

    /**
     * Lines from a bird to its sire and dam: a stub out, a vertical bar, a stub into each parent.
     *
     * @param  list<float>  $to
     */
    private function fork(float $fromX, float $fromY, float $toX, array $to): void
    {
        $middle = ($fromX + $toX) / 2;
        $this->line($fromX, $fromY, $middle, $fromY);
        $this->line($middle, min($to), $middle, max($to));

        foreach ($to as $y) {
            $this->line($middle, $y, $toX, $y);
        }
    }

    /**
     * The ♂ or ♀ in a round badge, drawn rather than typed (the font has no such symbols).
     */
    private function sexBadge(float $cx, float $cy, float $size, ?string $gender, bool $dark): void
    {
        if (! in_array($gender, ['male', 'female'], true)) {
            return;
        }

        $male = $gender === 'male';
        $background = $dark ? $this->mix(self::NAVY, self::IVORY, 0.1) : ($male ? '#e3ebf5' : '#f6e4ea');
        $color = $dark ? ($male ? '#9cc0ea' : '#f0a9bf') : ($male ? self::SIRE : self::DAM);
        $this->circle($cx, $cy, $size, $background);

        $radius = $size * 0.18;
        $stroke = $size * 0.085;

        if ($male) {
            [$ox, $oy] = [$cx - $size * 0.07, $cy + $size * 0.07];
            [$tx, $ty] = [$cx + $size * 0.25, $cy - $size * 0.25];
            $head = $size * 0.15;
            $this->line($ox, $oy, $tx - $stroke / 2, $ty + $stroke / 2, $stroke, $color);
            imagefilledpolygon($this->image, [
                $this->px($tx), $this->px($ty),
                $this->px($tx - $head), $this->px($ty),
                $this->px($tx), $this->px($ty + $head),
            ], $this->color($color));
        } else {
            [$ox, $oy] = [$cx, $cy - $size * 0.09];
            $this->line($ox, $oy, $ox, $cy + $size * 0.33, $stroke, $color);
            $this->line($ox - $size * 0.12, $cy + $size * 0.21, $ox + $size * 0.12, $cy + $size * 0.21, $stroke, $color);
        }

        $this->circle($ox, $oy, ($radius + $stroke / 2) * 2, $color);
        $this->circle($ox, $oy, ($radius - $stroke / 2) * 2, $background);
    }

    /**
     * Copy a photo into a rounded box, cropped to fill it.
     */
    private function photoInto(GdImage $photo, float $x, float $y, float $width, float $height, float $radius, string $background): void
    {
        [$sourceWidth, $sourceHeight] = [imagesx($photo), imagesy($photo)];
        $ratio = $width / $height;

        if ($sourceWidth / $sourceHeight > $ratio) {
            [$cropWidth, $cropHeight] = [$sourceHeight * $ratio, $sourceHeight];
        } else {
            [$cropWidth, $cropHeight] = [$sourceWidth, $sourceWidth / $ratio];
        }

        [$dx, $dy, $dw, $dh] = [$this->px($x), $this->px($y), $this->px($width), $this->px($height)];
        imagecopyresampled(
            $this->image, $photo, $dx, $dy,
            (int) round(($sourceWidth - $cropWidth) / 2), (int) round(($sourceHeight - $cropHeight) / 2),
            $dw, $dh, (int) round($cropWidth), (int) round($cropHeight),
        );

        // Round the corners: paint what lies outside each corner's arc in the card's color
        $r = $radius * self::SCALE;
        $fill = $this->color($background);
        for ($i = 0; $i < ceil($r); $i++) {
            for ($j = 0; $j < ceil($r); $j++) {
                if (hypot($r - $i - 0.5, $r - $j - 0.5) > $r) {
                    imagesetpixel($this->image, $dx + $i, $dy + $j, $fill);
                    imagesetpixel($this->image, $dx + $dw - 1 - $i, $dy + $j, $fill);
                    imagesetpixel($this->image, $dx + $i, $dy + $dh - 1 - $j, $fill);
                    imagesetpixel($this->image, $dx + $dw - 1 - $i, $dy + $dh - 1 - $j, $fill);
                }
            }
        }
    }

    // Drawing in final-size pixels; everything is scaled to the canvas here

    private function px(float $value): int
    {
        return (int) round($value * self::SCALE);
    }

    private function rect(float $x, float $y, float $width, float $height, string $color): void
    {
        imagefilledrectangle(
            $this->image, $this->px($x), $this->px($y),
            $this->px($x + $width) - 1, $this->px($y + $height) - 1, $this->color($color),
        );
    }

    private function roundedRect(float $x, float $y, float $width, float $height, float $radius, string $color): void
    {
        [$x1, $y1, $x2, $y2] = [$this->px($x), $this->px($y), $this->px($x + $width) - 1, $this->px($y + $height) - 1];
        $r = min($this->px($radius), intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2));
        $fill = $this->color($color);

        imagefilledrectangle($this->image, $x1 + $r, $y1, $x2 - $r, $y2, $fill);
        imagefilledrectangle($this->image, $x1, $y1 + $r, $x2, $y2 - $r, $fill);

        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($this->image, $cx, $cy, $r * 2, $r * 2, $fill);
        }
    }

    private function circle(float $cx, float $cy, float $diameter, string $color): void
    {
        imagefilledellipse(
            $this->image, $this->px($cx), $this->px($cy), $this->px($diameter), $this->px($diameter), $this->color($color),
        );
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $thickness = 1.5, string $color = self::LINE): void
    {
        imagesetthickness($this->image, max(1, $this->px($thickness)));
        imageline($this->image, $this->px($x1), $this->px($y1), $this->px($x2), $this->px($y2), $this->color($color));
        imagesetthickness($this->image, 1);
    }

    /**
     * Write text at a baseline; $tracking is extra space between letters, in ems. Returns the width written.
     */
    private function text(string $text, float $x, float $baseline, float $px, string $weight, string $color, float $tracking = 0): float
    {
        if ($text === '') {
            return 0;
        }

        $color = $this->color($color);

        if (! $tracking) {
            imagettftext($this->image, $this->points($px), 0, $this->px($x), $this->px($baseline), $color, $this->font($weight), $text);

            return $this->width($text, $px, $weight);
        }

        $cursor = $x;
        foreach (mb_str_split($text) as $char) {
            imagettftext($this->image, $this->points($px), 0, $this->px($cursor), $this->px($baseline), $color, $this->font($weight), $char);
            $cursor += $this->advance($char, $px, $weight) + $tracking * $px;
        }

        return $cursor - $x - $tracking * $px;
    }

    private function width(string $text, float $px, string $weight, float $tracking = 0): float
    {
        if ($text === '') {
            return 0;
        }

        if ($tracking) {
            $chars = mb_str_split($text);

            return array_sum(array_map(fn (string $char) => $this->advance($char, $px, $weight), $chars))
                + $tracking * $px * (count($chars) - 1);
        }

        $box = imagettfbbox($this->points($px), 0, $this->font($weight), $text);

        return ($box[2] - $box[0]) / self::SCALE;
    }

    /**
     * How far one character moves the pen (a space has no ink, so it is measured between two letters).
     */
    private function advance(string $char, float $px, string $weight): float
    {
        return $this->width("H{$char}H", $px, $weight) - $this->width('HH', $px, $weight);
    }

    /**
     * The baseline that centers capital letters in a box.
     */
    private function baseline(float $top, float $height, float $px, string $weight): float
    {
        return $top + ($height + $this->cap($px, $weight)) / 2;
    }

    private function cap(float $px, string $weight): float
    {
        return $this->capHeights["{$px}:{$weight}"] ??= (function () use ($px, $weight) {
            $box = imagettfbbox($this->points($px), 0, $this->font($weight), 'H');

            return ($box[1] - $box[7]) / self::SCALE;
        })();
    }

    /**
     * Cut text to $max wide, ending in an ellipsis when cut.
     */
    private function fit(string $text, float $px, string $weight, float $max): string
    {
        if ($this->width($text, $px, $weight) <= $max) {
            return $text;
        }

        $chars = mb_str_split($text);
        while ($chars && $this->width(rtrim(implode('', $chars)).'…', $px, $weight) > $max) {
            array_pop($chars);
        }

        return $chars ? rtrim(implode('', $chars)).'…' : '';
    }

    /**
     * Wrap text into at most $lines lines of $max wide; the last line is cut if the text doesn't fit.
     *
     * @return list<string>
     */
    private function wrap(string $text, float $px, string $weight, float $max, int $lines): array
    {
        $result = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = trim("{$line} {$word}");

            if ($line !== '' && count($result) < $lines - 1 && $this->width($candidate, $px, $weight) > $max) {
                $result[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        $result[] = $line;

        return array_map(fn (string $line) => $this->fit($line, $px, $weight, $max), $result);
    }

    /**
     * The logo, already at drawing size (twice the final size): resources/images/pedigree-preview holds copies of
     * public/icons/long-logo.webp (wordmark.png, 92px high) and logo.webp (crest.png, 264px), scaled down once so
     * each preview needn't decode the full-size artwork. Make them again if the logo changes.
     */
    private function asset(string $name): GdImage
    {
        return imagecreatefrompng(resource_path("images/pedigree-preview/{$name}"));
    }

    private function font(string $weight): string
    {
        return resource_path('fonts/'.self::FONTS[$weight]);
    }

    /**
     * GD sizes text in points at 96 dpi.
     */
    private function points(float $px): float
    {
        return $px * self::SCALE * 0.75;
    }

    private function color(string $hex): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return imagecolorallocate($this->image, $r, $g, $b);
    }

    /**
     * $from moved $amount of the way to $to, as one opaque color.
     */
    private function mix(string $from, string $to, float $amount): string
    {
        $a = sscanf(ltrim($from, '#'), '%02x%02x%02x');
        $b = sscanf(ltrim($to, '#'), '%02x%02x%02x');

        return vsprintf('#%02x%02x%02x', array_map(
            fn (int $i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $amount),
            [0, 1, 2],
        ));
    }

    /**
     * White or navy chip text, whichever has more contrast on the merit's color (as meritTextColor() in merits.ts).
     */
    private function chipTextColor(string $hex): string
    {
        $background = $this->luminance($hex);
        $onWhite = 1.05 / ($background + 0.05);
        $onNavy = ($background + 0.05) / ($this->luminance(self::NAVY) + 0.05);

        return $onWhite >= $onNavy ? self::WHITE : self::NAVY;
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel) {
            $channel /= 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
