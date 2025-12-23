<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auction extends Model
{
    protected $fillable = [
        'user_id',
        'pigeon_id',
        'starting_price',
        'current_price',
        'increment',
        'reserve_price',
        'deadline',
        'description',
        'additional_photos',
        'status',
        'winning_bidder_id',
    ];

    protected $casts = [
        'additional_photos' => 'array',
        'deadline' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }

    public function winningBidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winning_bidder_id');
    }
}
