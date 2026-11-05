<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveConnection extends Model
{
    protected $fillable = [
        'user_id',
        'google_email',
        'refresh_token',
        'access_token',
        'access_token_expires_at',
        'folder_id',
    ];

    protected $hidden = [
        'refresh_token',
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
        ];
    }

    public static function forUser(User $user): ?self
    {
        return static::where('user_id', $user->id)->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folderUrl(): ?string
    {
        return $this->folder_id ? 'https://drive.google.com/drive/folders/'.rawurlencode($this->folder_id) : null;
    }
}
