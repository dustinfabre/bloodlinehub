<?php

namespace App\Models;

use App\Services\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message sent from the feedback page: a bug, an idea, a question or anything else. Read in the admin inbox.
 */
class Feedback extends Model
{
    protected $table = 'feedback';

    public const CATEGORIES = ['bug', 'idea', 'question', 'other'];

    protected $fillable = [
        'user_id',
        'category',
        'message',
        'page_url',
        'user_agent',
        'screenshot_path',
    ];

    protected $hidden = [
        'screenshot_path',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (Feedback $feedback) => app(PrivateFiles::class)->delete($feedback->screenshot_path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeInbox(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at')->whereNull('archived_at');
    }
}
