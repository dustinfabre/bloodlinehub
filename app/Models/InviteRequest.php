<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An email left on the "request an invite" page while registration is closed. List them with `php artisan
 * invites:list`.
 */
class InviteRequest extends Model
{
    protected $fillable = [
        'email',
    ];
}
