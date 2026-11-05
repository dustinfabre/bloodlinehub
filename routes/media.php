<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Images stored in users' Google Drives. Loaded outside the web middleware group (bootstrap/app.php): no session and no
// cookies, so a CDN in front of the app can cache the responses.
Route::get('media/{file}.webp', MediaController::class)
    ->whereUuid('file')
    ->middleware('throttle:media')
    ->name('media.show');
