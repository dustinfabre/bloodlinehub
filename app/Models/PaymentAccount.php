<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bank or e-wallet account users send their payment to. Local accounts receive pesos; international accounts
 * receive dollars from payers abroad. Set up by an admin in /admin/bank-accounts.
 *
 * An international account can also be PayPal (method "paypal"): its bank_name is "PayPal" and account_number holds
 * the PayPal email, so it reads "PayPal · you@example.com" on payments like any other account.
 */
class PaymentAccount extends Model
{
    public const LOCAL = 'local';

    public const INTERNATIONAL = 'international';

    public const REGIONS = [self::LOCAL, self::INTERNATIONAL];

    public const ACCOUNT_TYPES = ['checking', 'savings'];

    public const BANK = 'bank';

    public const PAYPAL = 'paypal';

    public const METHODS = [self::BANK, self::PAYPAL];

    public const CURRENCY_LOCAL = 'PHP';

    public const CURRENCY_INTERNATIONAL = 'USD';

    protected $fillable = [
        'region',
        'method',
        'bank_name',
        'account_name',
        'account_number',
        'account_type',
        'routing_number',
        'swift_code',
        'bank_address',
        'instructions',
        'is_active',
        'sort_order',
    ];

    protected $hidden = [
        'qr_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // The currency follows the region: local accounts take pesos, international accounts dollars
        static::saving(function (PaymentAccount $account) {
            // PayPal is for payers abroad and has no bank details
            if ($account->method === self::PAYPAL) {
                $account->region = self::INTERNATIONAL;
                $account->bank_name = 'PayPal';
                $account->account_type = null;
                $account->routing_number = null;
                $account->swift_code = null;
                $account->bank_address = null;
            }

            $account->currency = self::currencyFor($account->region);
        });
    }

    public static function currencyFor(string $region): string
    {
        return $region === self::INTERNATIONAL ? self::CURRENCY_INTERNATIONAL : self::CURRENCY_LOCAL;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * How the account reads on a payment record, e.g. "BDO · 0012 3456 7890".
     */
    public function label(): string
    {
        return $this->bank_name.' · '.$this->account_number;
    }

    /**
     * What payers see: the details to send money to, and whether there is a QR code to scan.
     *
     * @return array<string, mixed>
     */
    public function toPayerArray(): array
    {
        return [
            'id' => $this->id,
            'region' => $this->region,
            'method' => $this->method ?? self::BANK,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'account_type' => $this->account_type,
            'routing_number' => $this->routing_number,
            'swift_code' => $this->swift_code,
            'bank_address' => $this->bank_address,
            'currency' => $this->currency,
            'instructions' => $this->instructions,
            'has_qr' => $this->qr_path !== null,
            // Changes whenever the account is saved, so a replaced QR code gets a new URL past the browser cache
            'qr_version' => $this->updated_at?->timestamp,
        ];
    }
}
