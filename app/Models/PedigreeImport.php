<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pedigree sheet (photo, scan or PDF) uploaded to fill in a bird and its ancestors without typing them.
 *
 * pending → reading → ready (or failed) → saved (or discarded). The sheet stays on the private disk while the
 * import is open, so the user can see it next to what was read; it is deleted once the import is saved or
 * discarded. Rows are kept after that, so the import limits count every sheet that was read.
 */
class PedigreeImport extends Model
{
    public const PENDING = 'pending';

    public const READING = 'reading';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const SAVED = 'saved';

    public const DISCARDED = 'discarded';

    // Still on the import page: waiting, being read, to check, or to retry
    public const OPEN = [self::PENDING, self::READING, self::READY, self::FAILED];

    protected $fillable = [
        'user_id',
        'status',
        'file_path',
        'file_mime',
        'file_name',
        'draft',
        'error',
        'attempts',
        'usage',
        'pigeon_id',
        'started_at',
    ];

    protected $casts = [
        'draft' => 'array',
        'usage' => 'array',
        'attempts' => 'integer',
        'started_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The sheet lives on the private disk, outside the database cascade
        static::deleting(function (PedigreeImport $import) {
            app(\App\Services\PrivateFiles::class)->delete($import->file_path);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isPdf(): bool
    {
        return $this->file_mime === 'application/pdf';
    }
}
