<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What Premium costs in pesos and dollars, and how long one payment lasts. There is one paid plan, Premium (the row
 * with key "premium"); everything else is free. An admin changes its prices in /admin/plans.
 *
 * Other rows are plans from before there was only Premium: hidden, and kept for the payments that point at them.
 */
class Plan extends Model
{
    public const PREMIUM = 'premium';

    // How long one payment of the price lasts, in months
    public const DURATIONS = [1, 3, 6, 12];

    protected $fillable = [
        'name',
        'description',
        'duration_months',
        'trial_days',
        'price_php',
        'price_usd',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'trial_days' => 'integer',
        'price_php' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The Premium plan, or null if it is missing (the migration creates it).
     */
    public static function premium(): ?self
    {
        return static::where('key', self::PREMIUM)->first();
    }

    public static function premiumOrCreate(): self
    {
        return static::premium() ?? tap(new static, fn (self $plan) => $plan->forceFill([
            'key' => self::PREMIUM,
            'name' => 'Premium',
            'duration_months' => 12,
            'trial_days' => 14,
            'price_php' => 0,
            'is_active' => false,
        ])->save());
    }

    /**
     * On sale once it has a peso price.
     */
    public function isOnSale(): bool
    {
        return $this->is_active && (float) $this->price_php > 0;
    }

    /**
     * The price as the public pricing shows it.
     *
     * @return array{on_sale: bool, duration_months: int, trial_days: int, price_php: ?string, price_usd: ?string}
     */
    public function toPricingArray(): array
    {
        $onSale = $this->isOnSale();

        return [
            'on_sale' => $onSale,
            'duration_months' => $this->duration_months,
            'trial_days' => $this->trial_days,
            'price_php' => $onSale ? $this->price_php : null,
            'price_usd' => $onSale ? $this->price_usd : null,
        ];
    }

    /**
     * The pricing shown on the public pages, with no plan set up treated as not on sale.
     *
     * @return array{on_sale: bool, duration_months: int, trial_days: int, price_php: ?string, price_usd: ?string}
     */
    public static function publicPricing(): array
    {
        return static::premium()?->toPricingArray()
            ?? ['on_sale' => false, 'duration_months' => 12, 'trial_days' => 0, 'price_php' => null, 'price_usd' => null];
    }

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
