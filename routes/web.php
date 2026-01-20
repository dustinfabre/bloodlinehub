<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubSeasonController;
use App\Http\Controllers\ClubSeasonRaceController;
use App\Http\Controllers\ClutchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\OlrRaceController;
use App\Http\Controllers\OlrSeasonController;
use App\Http\Controllers\OlrSeasonRaceController;
use App\Http\Controllers\PairingController;
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

    // Pairings management
    Route::resource('pairings', PairingController::class);
    Route::post('pairings/{pairing}/end-session', [PairingController::class, 'endSession'])
        ->name('pairings.end-session');
    
    // Clutches management (nested under pairings)
    Route::post('pairings/{pairing}/clutches', [ClutchController::class, 'store'])
        ->name('pairings.clutches.store');
    Route::patch('pairings/{pairing}/clutches/{clutch}', [ClutchController::class, 'update'])
        ->name('pairings.clutches.update');
    Route::delete('pairings/{pairing}/clutches/{clutch}', [ClutchController::class, 'destroy'])
        ->name('pairings.clutches.destroy');

    // Sales management (authenticated)
    Route::get('sales', [SalesController::class, 'index'])
        ->name('sales.index');
    Route::post('sales', [SalesController::class, 'store'])
        ->name('sales.store');
    Route::delete('sales/{sale}', [SalesController::class, 'destroy'])
        ->name('sales.destroy');

    // OLR Races management
    Route::resource('olr-races', OlrRaceController::class);
    
    // OLR Seasons
    Route::get('olr-races/{olr_race}/seasons/create', [OlrSeasonController::class, 'create'])
        ->name('olr-races.seasons.create');
    Route::post('olr-races/{olr_race}/seasons', [OlrSeasonController::class, 'store'])
        ->name('olr-races.seasons.store');
    Route::get('olr-races/{olr_race}/seasons/{season}', [OlrSeasonController::class, 'show'])
        ->name('olr-races.seasons.show');
    Route::get('olr-races/{olr_race}/seasons/{season}/edit', [OlrSeasonController::class, 'edit'])
        ->name('olr-races.seasons.edit');
    Route::patch('olr-races/{olr_race}/seasons/{season}', [OlrSeasonController::class, 'update'])
        ->name('olr-races.seasons.update');
    Route::delete('olr-races/{olr_race}/seasons/{season}', [OlrSeasonController::class, 'destroy'])
        ->name('olr-races.seasons.destroy');
    
    // OLR Season Entries
    Route::post('olr-races/{olr_race}/seasons/{season}/entries', [OlrSeasonController::class, 'addEntry'])
        ->name('olr-races.seasons.entries.store');
    Route::delete('olr-races/{olr_race}/seasons/{season}/entries/{pigeon}', [OlrSeasonController::class, 'removeEntry'])
        ->name('olr-races.seasons.entries.destroy');
    Route::patch('olr-races/{olr_race}/seasons/{season}/entries/{pigeon}', [OlrSeasonController::class, 'updateEntry'])
        ->name('olr-races.seasons.entries.update');
    
    // OLR Season Races
    Route::get('olr-races/{olr_race}/seasons/{season}/races/create', [OlrSeasonRaceController::class, 'create'])
        ->name('olr-races.seasons.races.create');
    Route::post('olr-races/{olr_race}/seasons/{season}/races', [OlrSeasonRaceController::class, 'store'])
        ->name('olr-races.seasons.races.store');
    Route::get('olr-races/{olr_race}/seasons/{season}/races/{race}', [OlrSeasonRaceController::class, 'show'])
        ->name('olr-races.seasons.races.show');
    Route::get('olr-races/{olr_race}/seasons/{season}/races/{race}/edit', [OlrSeasonRaceController::class, 'edit'])
        ->name('olr-races.seasons.races.edit');
    Route::patch('olr-races/{olr_race}/seasons/{season}/races/{race}', [OlrSeasonRaceController::class, 'update'])
        ->name('olr-races.seasons.races.update');
    Route::delete('olr-races/{olr_race}/seasons/{season}/races/{race}', [OlrSeasonRaceController::class, 'destroy'])
        ->name('olr-races.seasons.races.destroy');
    
    // OLR Race Results
    Route::post('olr-races/{olr_race}/seasons/{season}/races/{race}/results', [OlrSeasonRaceController::class, 'addResult'])
        ->name('olr-races.seasons.races.results.store');
    Route::delete('olr-races/{olr_race}/seasons/{season}/races/{race}/results/{pigeon}', [OlrSeasonRaceController::class, 'removeResult'])
        ->name('olr-races.seasons.races.results.destroy');
    Route::patch('olr-races/{olr_race}/seasons/{season}/races/{race}/results/{pigeon}', [OlrSeasonRaceController::class, 'updateResult'])
        ->name('olr-races.seasons.races.results.update');
    Route::post('olr-races/{olr_race}/seasons/{season}/races/{race}/add-all-entries', [OlrSeasonRaceController::class, 'addAllEntries'])
        ->name('olr-races.seasons.races.add-all-entries');

    // Clubs management
    Route::resource('clubs', ClubController::class);
    
    // Club Seasons
    Route::get('clubs/{club}/seasons/create', [ClubSeasonController::class, 'create'])
        ->name('clubs.seasons.create');
    Route::post('clubs/{club}/seasons', [ClubSeasonController::class, 'store'])
        ->name('clubs.seasons.store');
    Route::get('clubs/{club}/seasons/{season}', [ClubSeasonController::class, 'show'])
        ->name('clubs.seasons.show');
    Route::get('clubs/{club}/seasons/{season}/edit', [ClubSeasonController::class, 'edit'])
        ->name('clubs.seasons.edit');
    Route::patch('clubs/{club}/seasons/{season}', [ClubSeasonController::class, 'update'])
        ->name('clubs.seasons.update');
    Route::delete('clubs/{club}/seasons/{season}', [ClubSeasonController::class, 'destroy'])
        ->name('clubs.seasons.destroy');
    
    // Club Season Entries
    Route::post('clubs/{club}/seasons/{season}/entries', [ClubSeasonController::class, 'addEntry'])
        ->name('clubs.seasons.entries.store');
    Route::delete('clubs/{club}/seasons/{season}/entries/{pigeon}', [ClubSeasonController::class, 'removeEntry'])
        ->name('clubs.seasons.entries.destroy');
    Route::patch('clubs/{club}/seasons/{season}/entries/{pigeon}', [ClubSeasonController::class, 'updateEntry'])
        ->name('clubs.seasons.entries.update');
    
    // Club Season Races
    Route::get('clubs/{club}/seasons/{season}/races/create', [ClubSeasonRaceController::class, 'create'])
        ->name('clubs.seasons.races.create');
    Route::post('clubs/{club}/seasons/{season}/races', [ClubSeasonRaceController::class, 'store'])
        ->name('clubs.seasons.races.store');
    Route::get('clubs/{club}/seasons/{season}/races/{race}', [ClubSeasonRaceController::class, 'show'])
        ->name('clubs.seasons.races.show');
    Route::get('clubs/{club}/seasons/{season}/races/{race}/edit', [ClubSeasonRaceController::class, 'edit'])
        ->name('clubs.seasons.races.edit');
    Route::patch('clubs/{club}/seasons/{season}/races/{race}', [ClubSeasonRaceController::class, 'update'])
        ->name('clubs.seasons.races.update');
    Route::delete('clubs/{club}/seasons/{season}/races/{race}', [ClubSeasonRaceController::class, 'destroy'])
        ->name('clubs.seasons.races.destroy');
    
    // Club Race Results
    Route::post('clubs/{club}/seasons/{season}/races/{race}/results', [ClubSeasonRaceController::class, 'addResult'])
        ->name('clubs.seasons.races.results.store');
    Route::delete('clubs/{club}/seasons/{season}/races/{race}/results/{pigeon}', [ClubSeasonRaceController::class, 'removeResult'])
        ->name('clubs.seasons.races.results.destroy');
    Route::patch('clubs/{club}/seasons/{season}/races/{race}/results/{pigeon}', [ClubSeasonRaceController::class, 'updateResult'])
        ->name('clubs.seasons.races.results.update');
    Route::post('clubs/{club}/seasons/{season}/races/{race}/add-all-entries', [ClubSeasonRaceController::class, 'addAllEntries'])
        ->name('clubs.seasons.races.add-all-entries');
});

require __DIR__.'/settings.php';
