<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'pigeon_id',
        'price',
        'hide_price',
        'description',
        'additional_photos',
        'status',
        'sold_at',
    ];

    protected $casts = [
        'additional_photos' => 'array',
        'hide_price' => 'boolean',
        'sold_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }
}
