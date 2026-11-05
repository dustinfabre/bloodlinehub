<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Breeder details shown on all of a user's pedigrees. Contact details only appear when switched on.
 */
class BreederProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'phone',
        'email',
        'address',
        'show_phone',
        'show_email',
        'show_address',
    ];

    protected $casts = [
        'show_phone' => 'boolean',
        'show_email' => 'boolean',
        'show_address' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contact details for the pedigree sheet: hidden or empty ones are null, so they never reach a shared page.
     *
     * @return array{phone: ?string, email: ?string, address: ?string}
     */
    public function pedigreeContact(): array
    {
        return [
            'phone' => $this->show_phone && filled($this->phone) ? $this->phone : null,
            'email' => $this->show_email && filled($this->email) ? $this->email : null,
            'address' => $this->show_address && filled($this->address) ? $this->address : null,
        ];
    }
}
