<?php

namespace App\Models;

use App\Services\MailInBackground;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Accounts must verify their email before using the app: the 'verified' route middleware sends unverified users to
 * the "check your email" page (auth/VerifyEmail).
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'premium_until' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Feedback rows go with the account (a database cascade), but their screenshots live on R2, so delete them
        // one by one first. Payments are kept: they are business records (see Payment).
        static::deleting(function (User $user) {
            $user->feedback()->get()->each->delete();
        });
    }

    /**
     * The verification link (on sign-up, an email change, or "Resend"), sent after the response: a slow or failing
     * mail server must not break sign-up, and a failure is logged.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(MailInBackground::class)->send($this, new VerifyEmail);
    }

    /**
     * Premium while premium_until is in the future.
     */
    public function isPremium(): bool
    {
        return $this->premium_until !== null && $this->premium_until->isFuture();
    }

    /**
     * In the free trial, which is full Premium.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Never had a free trial (each account gets one).
     */
    public function canStartTrial(): bool
    {
        return $this->trial_ends_at === null;
    }

    /**
     * Start the free trial: $days of full Premium from now. Does nothing if the account already had one.
     */
    public function startTrial(int $days): bool
    {
        if ($days < 1 || ! $this->canStartTrial()) {
            return false;
        }

        return $this->forceFill(['trial_ends_at' => now()->addDays($days)])->save();
    }

    /**
     * Can use Premium features: paid Premium, the free trial, or an admin (who needs to see everything to support
     * users).
     */
    public function hasPremium(): bool
    {
        return $this->is_admin || $this->isPremium() || $this->isOnTrial();
    }

    public function pigeons(): HasMany
    {
        return $this->hasMany(Pigeon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Breeder details printed on pedigrees (none until the user fills them in).
     */
    public function breederProfile(): HasOne
    {
        return $this->hasOne(BreederProfile::class);
    }
}
