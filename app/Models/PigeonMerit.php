<?php

namespace App\Models;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

class PigeonMerit extends Model
{
    protected $fillable = [
        'pigeon_id',
        'type',
        'level',
        'year',
        'detail',
        'source',
    ];

    protected $casts = [
        'type' => MeritType::class,
        'level' => MeritLevel::class,
        'year' => 'integer',
    ];

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }

    /**
     * @return array{id: ?int, type: string, label: string, code: string, category: string, level: ?string, level_label: ?string, year: ?int, detail: ?string, short: string}
     */
    public function summary(): array
    {
        return self::summarize($this->type, $this->level, $this->year, $this->detail, $this->id);
    }

    /**
     * The same shape for merits that are not rows here: external ancestors' merits and suggestions.
     */
    public static function summarize(MeritType $type, ?MeritLevel $level, ?int $year, ?string $detail, ?int $id = null): array
    {
        return [
            'id' => $id,
            'type' => $type->value,
            'label' => $type->label(),
            'code' => $type->code(),
            'category' => $type->category(),
            'level' => $level?->value,
            'level_label' => $level?->label(),
            'year' => $year,
            'detail' => $detail,
            // Pedigree-style one-liner, e.g. "ACE Nat '24"
            'short' => trim(implode(' ', array_filter([
                $type->code(),
                $level?->abbreviation(),
                $year ? "'".substr((string) $year, -2) : null,
            ]))),
        ];
    }

    /**
     * Validation rules for a merit list sent with an external ancestor.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function listRules(string $field): array
    {
        return [
            $field => ['nullable', 'array', 'max:12'],
            "{$field}.*.type" => ['required', Rule::enum(MeritType::class)],
            "{$field}.*.level" => ['nullable', Rule::enum(MeritLevel::class)],
            "{$field}.*.year" => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            "{$field}.*.detail" => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A validated merit list in its stored form, or null when empty.
     *
     * @param  array<int, array<string, mixed>>|null  $list
     * @return list<array{type: string, level: ?string, year: ?int, detail: ?string}>|null
     */
    public static function cleanList(?array $list): ?array
    {
        $clean = array_values(array_map(fn (array $item) => [
            'type' => (string) $item['type'],
            'level' => ($item['level'] ?? null) ?: null,
            'year' => isset($item['year']) && $item['year'] !== '' ? (int) $item['year'] : null,
            'detail' => isset($item['detail']) && trim((string) $item['detail']) !== '' ? trim((string) $item['detail']) : null,
        ], $list ?? []));

        return $clean ?: null;
    }

    /**
     * Summaries of a stored merit list ({type, level, year, detail} items, as kept for external ancestors).
     * Unknown types are skipped rather than failing the whole pedigree.
     *
     * @param  array<int, array<string, mixed>>|null  $list
     * @return list<array<string, mixed>>
     */
    public static function summarizeList(?array $list): array
    {
        return collect($list ?? [])
            ->map(function ($item) {
                $type = is_array($item) ? MeritType::tryFrom((string) ($item['type'] ?? '')) : null;

                return $type ? self::summarize(
                    $type,
                    MeritLevel::tryFrom((string) ($item['level'] ?? '')),
                    isset($item['year']) ? (int) $item['year'] : null,
                    $item['detail'] ?? null,
                ) : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
