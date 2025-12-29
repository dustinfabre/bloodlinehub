<?php

use App\Http\Controllers\ClubRaceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\OlrRaceController;
use App\Http\Controllers\PigeonController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))
    ->name('home');
// Public marketplace (no auth required)
Route::get('marketplace', [MarketplaceController::class, 'index'])
    ->name('marketplace.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::resource('pigeons', PigeonController::class);
    Route::get('pigeons/{pigeon}/pedigree', [PigeonController::class, 'pedigree'])
        ->name('pigeons.pedigree');
    Route::get('pigeons/{pigeon}/print-pedigree', [PigeonController::class, 'printPedigree'])
        ->name('pigeons.printPedigree');

    // Sales management (authenticated)
    Route::get('sales', [SalesController::class, 'index'])
        ->name('sales.index');
    Route::post('sales', [SalesController::class, 'store'])
        ->name('sales.store');
    Route::delete('sales/{sale}', [SalesController::class, 'destroy'])
        ->name('sales.destroy');

    // OLR Races management
    Route::resource('olr-races', OlrRaceController::class);
    Route::post('olr-races/{olr_race}/pigeons', [OlrRaceController::class, 'addPigeon'])
        ->name('olr-races.add-pigeon');
    Route::delete('olr-races/{olr_race}/pigeons/{pigeon}', [OlrRaceController::class, 'removePigeon'])
        ->name('olr-races.remove-pigeon');
    Route::patch('olr-races/{olr_race}/pigeons/{pigeon}', [OlrRaceController::class, 'updatePigeon'])
        ->name('olr-races.update-pigeon');

    // Club Races management
    Route::resource('club-races', ClubRaceController::class);
    Route::post('club-races/{club_race}/pigeons', [ClubRaceController::class, 'addPigeon'])
        ->name('club-races.add-pigeon');
    Route::delete('club-races/{club_race}/pigeons/{pigeon}', [ClubRaceController::class, 'removePigeon'])
        ->name('club-races.remove-pigeon');
    Route::patch('club-races/{club_race}/pigeons/{pigeon}', [ClubRaceController::class, 'updatePigeon'])
        ->name('club-races.update-pigeon');
});

require __DIR__.'/settings.php';
