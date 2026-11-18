<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\PedigreePreviewController;
use Illuminate\Support\Facades\Route;

// Images stored in users' Google Drives, and pedigree link previews. Loaded outside the web middleware group
// (bootstrap/app.php): no session and no cookies, so a CDN in front of the app can cache the responses.
Route::get('media/{file}.webp', MediaController::class)
    ->whereUuid('file')
    ->middleware('throttle:media')
    ->name('media.show');

// The picture shown when a public pedigree's link is posted on social media (og:image on /p/{serial})
Route::get('p/{serial}/preview.png', PedigreePreviewController::class)
    ->where('serial', '[A-Za-z0-9-]+')
    ->middleware('throttle:pedigree-preview')
    ->name('pedigrees.preview');
