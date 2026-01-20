<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clutch extends Model
{
    protected $fillable = [
        'pairing_id',
        'clutch_number',
        'eggs_laid_date',
        'hatched_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'eggs_laid_date' => 'date',
        'hatched_date' => 'date',
    ];

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(Pairing::class);
    }

    public function offspring(): HasMany
    {
        return $this->hasMany(Pigeon::class, 'clutch_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isUnsuccessful(): bool
    {
        return $this->status === 'unsuccessful';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsSuccessful(): void
    {
        $this->update(['status' => 'successful']);
    }

    public function markAsUnsuccessful(): void
    {
        $this->update(['status' => 'unsuccessful']);
    }
}
