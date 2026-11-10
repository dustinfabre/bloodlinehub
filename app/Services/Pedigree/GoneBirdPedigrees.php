<?php

namespace App\Services\Pedigree;

use App\Models\Pigeon;

/**
 * When birds go missing or die while their pedigree is public, the next page asks the owner whether to make those
 * pedigrees private, so whoever finds a lost bird can't look up its pedigree. Password-protected and private
 * pedigrees are already hidden and need no prompt.
 */
class GoneBirdPedigrees
{
    public const SESSION_KEY = 'pedigreePrompt';

    /**
     * Flash the prompt for those of $pigeonIds that are now missing or deceased and still have a public pedigree.
     *
     * @param  iterable<int>  $pigeonIds  Birds whose status just changed
     */
    public static function promptFor(iterable $pigeonIds): void
    {
        $ids = collect($pigeonIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $pigeons = Pigeon::query()
            ->whereIn('id', $ids)
            ->whereIn('status', Pigeon::GONE_STATUSES)
            ->whereHas('pedigree', fn ($query) => $query->where('is_public', true))
            ->orderBy('ring_number')
            ->get(['id', 'name', 'ring_number', 'personal_number', 'status']);

        if ($pigeons->isEmpty()) {
            return;
        }

        session()->flash(self::SESSION_KEY, [
            // Lets the page show each prompt once, even when the browser restores an earlier page from history
            'id' => (string) str()->uuid(),
            'pigeons' => $pigeons->map(fn (Pigeon $pigeon) => [
                'id' => $pigeon->id,
                'label' => $pigeon->ring_number ?: $pigeon->name ?: $pigeon->personal_number ?: "Pigeon #{$pigeon->id}",
                'status' => $pigeon->status,
            ])->all(),
        ]);
    }
}
