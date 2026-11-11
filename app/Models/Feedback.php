<?php

namespace App\Models;

use App\Services\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message sent from the feedback page: a bug, an idea, a question or anything else. Read in the admin inbox.
 */
class Feedback extends Model
{
    protected $table = 'feedback';

    public const CATEGORIES = ['bug', 'idea', 'question', 'other'];

    public const NEW = 'new';

    /**
     * Where a message stands, per category, in the order it usually goes. Every message starts "new".
     */
    public const STATUSES = [
        'bug' => ['new' => 'New', 'in_progress' => 'Working on it', 'fixed' => 'Fixed', 'cant_reproduce' => "Can't reproduce"],
        'idea' => ['new' => 'New', 'planned' => 'Planned', 'done' => 'Done', 'not_planned' => 'Not planned'],
        'question' => ['new' => 'Open', 'answered' => 'Answered'],
        'other' => ['new' => 'New', 'done' => 'Handled'],
    ];

    // Statuses that mean it is dealt with
    public const CLOSED = ['fixed', 'cant_reproduce', 'done', 'not_planned', 'answered'];

    protected $fillable = [
        'user_id',
        'category',
        'message',
        'page_url',
        'user_agent',
        'screenshot_path',
        'admin_note',
    ];

    protected $hidden = [
        'screenshot_path',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'starred_at' => 'datetime',
        'status_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (Feedback $feedback) => app(PrivateFiles::class)->delete($feedback->screenshot_path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(FeedbackLabel::class, 'feedback_feedback_label');
    }

    /**
     * The earlier message this one asks the same thing as.
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /**
     * Later messages marked as asking the same thing ("me too").
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    /**
     * @return array<string, string> status => label for this message's category
     */
    public function statuses(): array
    {
        return self::STATUSES[$this->category] ?? self::STATUSES['other'];
    }

    public function statusLabel(): string
    {
        return $this->statuses()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED);
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
