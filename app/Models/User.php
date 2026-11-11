<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
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
     * Premium while premium_until is in the future.
     */
    public function isPremium(): bool
    {
        return $this->premium_until !== null && $this->premium_until->isFuture();
    }

    /**
     * Can use Premium features: paid Premium, or an admin (who needs to see everything to support users).
     */
    public function hasPremium(): bool
    {
        return $this->is_admin || $this->isPremium();
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
