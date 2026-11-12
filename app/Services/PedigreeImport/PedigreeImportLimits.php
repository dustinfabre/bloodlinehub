<?php

namespace App\Services\PedigreeImport;

use App\Models\PedigreeImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many more sheets an account can import (config/pedigree_import.php). Every sheet read costs money, so a sheet
 * counts once it is uploaded, unless it never reached the reader (removed while waiting, or the reader failed
 * without answering).
 */
class PedigreeImportLimits
{
    /**
     * @return array{left: int, free_left: ?int, free_limit: int, daily_limit: int}
     */
    public function allowance(User $user): array
    {
        $freeLimit = config('pedigree_import.free_limit');
        $dailyLimit = config('pedigree_import.daily_limit');

        $today = $this->counted($user)->where('created_at', '>=', now()->startOfDay())->count();
        $freeLeft = $user->hasPremium() ? null : max(0, $freeLimit - $this->counted($user)->count());
        $dailyLeft = max(0, $dailyLimit - $today);

        return [
            'left' => $freeLeft === null ? $dailyLeft : min($freeLeft, $dailyLeft),
            'free_left' => $freeLeft,
            'free_limit' => $freeLimit,
            'daily_limit' => $dailyLimit,
        ];
    }

    /**
     * Why the account can't import another sheet now, or null if it can.
     */
    public function refusal(User $user): ?string
    {
        $allowance = $this->allowance($user);

        if ($allowance['left'] > 0) {
            return null;
        }

        return $allowance['free_left'] === 0
            ? "Free accounts can import {$allowance['free_limit']} pedigrees. Upgrade to Premium to import as many as you like."
            : "You can import {$allowance['daily_limit']} pedigrees a day. Try again tomorrow.";
    }

    /**
     * @return Builder<PedigreeImport>
     */
    private function counted(User $user): Builder
    {
        return PedigreeImport::where('user_id', $user->id)->where(fn (Builder $query) => $query
            ->whereIn('status', [PedigreeImport::PENDING, PedigreeImport::READING, PedigreeImport::READY, PedigreeImport::SAVED])
            ->orWhereNotNull('usage'));
    }
}
