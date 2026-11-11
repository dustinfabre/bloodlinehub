<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Premium plan users can pay for by bank transfer: how long it lasts and what it costs in pesos and dollars.
 * Set up by an admin in /admin/plans.
 */
class Plan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'duration_months',
        'price_php',
        'price_usd',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price_php' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('duration_months')->orderBy('id');
    }

    /**
     * The price in a payment account's currency, or null when the plan isn't sold in it.
     */
    public function priceIn(string $currency): ?string
    {
        return match ($currency) {
            PaymentAccount::CURRENCY_LOCAL => $this->price_php,
            PaymentAccount::CURRENCY_INTERNATIONAL => $this->price_usd,
            default => null,
        };
    }
}
