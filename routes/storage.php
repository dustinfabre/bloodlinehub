<?php

use App\Http\Controllers\Settings\StorageController;
use Illuminate\Support\Facades\Route;

// Settings → Storage: each user connects their own Google Drive, where their photos are saved
Route::middleware('auth')->group(function () {
    Route::get('settings/storage', [StorageController::class, 'show'])->name('storage.edit');
    Route::get('settings/storage/google/connect', [StorageController::class, 'connect'])
        ->middleware('throttle:10,1')
        ->name('storage.google.connect');
    // The redirect URI registered in Google Cloud Console
    Route::get('settings/storage/google/callback', [StorageController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('storage.google.callback');
    Route::delete('settings/storage/google', [StorageController::class, 'destroy'])->name('storage.google.destroy');
});
