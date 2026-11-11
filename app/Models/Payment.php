<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank transfer a user made for a plan, with the receipt they uploaded. An admin checks the money arrived and
 * approves it, which extends the user's Premium, or rejects it with a note the user sees.
 *
 * Payment records outlive the account (user_id becomes null), so the payer's name and email, the plan and the
 * account paid to are copied onto the row.
 */
class Payment extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const STATUSES = [self::PENDING, self::APPROVED, self::REJECTED];

    protected $fillable = [
        'user_id',
        'payer_name',
        'payer_email',
        'plan_id',
        'plan_name',
        'duration_months',
        'payment_account_id',
        'paid_to',
        'currency',
        'amount',
        'reference_number',
        'paid_on',
        'payer_note',
        'receipt_path',
        'receipt_mime',
    ];

    protected $hidden = [
        'receipt_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'duration_months' => 'integer',
        'paid_on' => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
        'premium_from' => 'datetime',
        'premium_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
