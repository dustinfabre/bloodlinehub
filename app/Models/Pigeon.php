<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pigeon extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'color',
        'ring_number',
        'personal_number',
        'remarks',
        'notes',
        'photos',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
