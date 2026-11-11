<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label admins put on feedback to group it, e.g. "pedigree", "billing" or "mobile".
 */
class FeedbackLabel extends Model
{
    // Colors a label can have (the inbox maps each to its own badge style)
    public const COLORS = ['gray', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'violet', 'pink'];

    protected $fillable = [
        'name',
        'color',
    ];

    public function feedback(): BelongsToMany
    {
        return $this->belongsToMany(Feedback::class, 'feedback_feedback_label');
    }
}
