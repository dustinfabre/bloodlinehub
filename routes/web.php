<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BloodlineController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubSeasonController;
use App\Http\Controllers\ClubSeasonEventController;
use App\Http\Controllers\ClubSeasonRaceController;
use App\Http\Controllers\ClutchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LineageController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LoftCheckController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MeritSuggestionController;
use App\Http\Controllers\OlrRaceController;
use App\Http\Controllers\OlrSeasonController;
use App\Http\Controllers\OlrSeasonRaceController;
use App\Http\Controllers\PairingAnalysisController;
use App\Http\Controllers\PairingController;
use App\Http\Controllers\PedigreeAncestorController;
use App\Http\Controllers\PedigreeController;
use App\Http\Controllers\PedigreeCopyController;
use App\Http\Controllers\PigeonController;
use App\Http\Controllers\PigeonMeritController;
use App\Http\Controllers\PlannedPairingController;
use App\Http\Controllers\SalesController;
use App\Http\Middleware\NoIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))
    ->name('home');
// Legal pages, public (also the privacy policy URL on the Google OAuth consent screen)
Route::get('privacy', fn () => Inertia::render('legal/Privacy'))
    ->name('privacy');
Route::get('terms', fn () => Inertia::render('legal/Terms'))
    ->name('terms');
// Public marketplace (no auth required)
Route::get('marketplace', [MarketplaceController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('marketplace.index');
// Sign-up. Fortify's registration feature stays off (config/fortify.php) so these routes own /register and can add
// a honeypot, a rate limit and the terms checkbox.
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});
// Shared pedigree (QR code target); private pedigrees are only visible to their owner or with the share password.
// Kept out of search engines, so a lost bird's pedigree can't be found by searching its ring number.
Route::get('p/{serial}', [PedigreeController::class, 'shared'])
    ->middleware(['throttle:60,1', NoIndex::class])
    ->name('pedigrees.shared');
Route::post('p/{serial}/unlock', [PedigreeController::class, 'unlock'])
    ->middleware(['throttle:10,1,pedigree-unlock', NoIndex::class])
    ->name('pedigrees.unlock');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Custom pigeon routes must be defined BEFORE the resource route
    Route::get('pigeons/check-ring-number', [PigeonController::class, 'checkRingNumber'])
        ->name('pigeons.check-ring-number');
    Route::get('pigeons/suggest-ring-number', [PigeonController::class, 'suggestRingNumber'])
        ->name('pigeons.suggest-ring-number');

    Route::resource('pigeons', PigeonController::class);
    Route::patch('pigeons/{pigeon}/status', [PigeonController::class, 'updateStatus'])
        ->name('pigeons.status');
    Route::get('pigeons/{pigeon}/pedigree', [PedigreeController::class, 'show'])
        ->name('pigeons.pedigree');
    Route::get('pigeons/{pigeon}/print-pedigree', [PedigreeController::class, 'print'])
        ->name('pigeons.printPedigree');
    Route::patch('pigeons/{pigeon}/pedigree/visibility', [PedigreeController::class, 'updateVisibility'])
        ->name('pigeons.pedigreeVisibility');
    Route::patch('pedigrees/private', [PedigreeController::class, 'makePrivate'])
        ->name('pedigrees.makePrivate');
    Route::get('pigeons/{pigeon}/pedigree/edit', [PedigreeAncestorController::class, 'edit'])
        ->name('pigeons.editPedigree');
    Route::put('pigeons/{pigeon}/pedigree/ancestors', [PedigreeAncestorController::class, 'update'])
        ->name('pigeons.saveAncestor');
    Route::put('pigeons/{pigeon}/pedigree/notable-relatives', [PedigreeAncestorController::class, 'featureRelatives'])
        ->name('pigeons.featureRelatives');
    Route::delete('pigeons/{pigeon}/pedigree/ancestors', [PedigreeAncestorController::class, 'destroy'])
        ->name('pigeons.removeAncestor');
    // Copying ancestors from another bird's pedigree (Premium)
    Route::middleware('premium:pedigree-copy')->group(function () {
        Route::get('pigeons/{pigeon}/pedigree/copy-sources', [PedigreeCopyController::class, 'sources'])
            ->name('pigeons.pedigreeCopySources');
        Route::get('pigeons/{pigeon}/pedigree/copy-sources/{source}', [PedigreeCopyController::class, 'source'])
            ->name('pigeons.pedigreeCopySource');
        Route::post('pigeons/{pigeon}/pedigree/copy', [PedigreeCopyController::class, 'store'])
            ->name('pigeons.copyPedigree');
    });
    Route::post('pigeons/{pigeon}/merits', [PigeonMeritController::class, 'store'])
        ->name('pigeons.merits.store');
    Route::delete('pigeons/{pigeon}/merits/{merit}', [PigeonMeritController::class, 'destroy'])
        ->name('pigeons.merits.destroy');

    // Merits suggested from race results and offspring
    Route::post('merit-suggestions/accept', [MeritSuggestionController::class, 'accept'])
        ->name('merit-suggestions.accept');
    Route::post('merit-suggestions/dismiss', [MeritSuggestionController::class, 'dismiss'])
        ->name('merit-suggestions.dismiss');

    // Lineage: descendants, siblings and shared descendants of two birds
    Route::get('lineage', [LineageController::class, 'index'])
        ->name('lineage.index');
    Route::get('lineage/search', [LineageController::class, 'search'])
        ->name('lineage.search');

    // Pairing Analysis: relationship, inbreeding and proof of a planned cross, and the breeding plan on the same page
    // (Premium)
    Route::middleware('premium:pairing-analysis')->group(function () {
        Route::get('pairing-analysis', [PairingAnalysisController::class, 'index'])
            ->name('pairing-analysis.index');

        // Breeding plan: pairs saved for next season
        Route::post('planned-pairings', [PlannedPairingController::class, 'store'])
            ->name('planned-pairings.store');
        Route::patch('planned-pairings/{plannedPairing}', [PlannedPairingController::class, 'update'])
            ->name('planned-pairings.update');
        Route::delete('planned-pairings/{plannedPairing}', [PlannedPairingController::class, 'destroy'])
            ->name('planned-pairings.destroy');
    });
    // Its old address (it was called Test Pairing), for bookmarks
    Route::get('test-pairing', fn (Request $request) => redirect()->route('pairing-analysis.index', $request->query()));

    // Bloodlines management API
    Route::get('bloodlines', [BloodlineController::class, 'index'])
        ->name('bloodlines.index');
    Route::get('bloodlines/search', [BloodlineController::class, 'search'])
        ->name('bloodlines.search');
    Route::post('bloodlines', [BloodlineController::class, 'store'])
        ->name('bloodlines.store');
    Route::post('bloodlines/get-or-create', [BloodlineController::class, 'getOrCreate'])
        ->name('bloodlines.get-or-create');
    Route::patch('bloodlines/{bloodline}', [BloodlineController::class, 'update'])
        ->name('bloodlines.update');
    Route::delete('bloodlines/{bloodline}', [BloodlineController::class, 'destroy'])
        ->name('bloodlines.destroy');

    // Loft checks: count the birds after a training toss, find the missing ones, keep the history (Premium)
    Route::middleware('premium:loft-check')->group(function () {
        Route::get('loft-checks', [LoftCheckController::class, 'index'])
            ->name('loft-checks.index');
        Route::get('loft-checks/create', [LoftCheckController::class, 'create'])
            ->name('loft-checks.create');
        Route::post('loft-checks', [LoftCheckController::class, 'store'])
            ->name('loft-checks.store');
        Route::get('loft-checks/{loftCheck}', [LoftCheckController::class, 'show'])
            ->name('loft-checks.show');
        Route::delete('loft-checks/{loftCheck}', [LoftCheckController::class, 'destroy'])
            ->name('loft-checks.destroy');
    });

    // Locations management
    Route::get('locations', [LocationController::class, 'index'])
        ->name('locations.index');
    Route::post('locations', [LocationController::class, 'store'])
        ->name('locations.store');
    Route::get('locations/{location}', [LocationController::class, 'show'])
        ->name('locations.show');
    // Add birds to a location (moving them from wherever they are) or take them off it
    Route::post('locations/{location}/pigeons', [LocationController::class, 'addPigeons'])
        ->name('locations.pigeons.store');
    Route::delete('locations/{location}/pigeons', [LocationController::class, 'removePigeons'])
        ->name('locations.pigeons.destroy');
    Route::patch('locations/{location}', [LocationController::class, 'update'])
        ->name('locations.update');
    Route::delete('locations/{location}', [LocationController::class, 'destroy'])
        ->name('locations.destroy');

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

    // Nested OLR routes: each {season}, {race} and {pigeon} must belong to the one before it (404 otherwise)
    Route::scopeBindings()->group(function () {
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
        Route::post('olr-races/{olr_race}/seasons/{season}/entries/bulk', [OlrSeasonController::class, 'addBulkEntries'])
            ->name('olr-races.seasons.entries.bulk');
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
    });

    // Clubs management
    Route::resource('clubs', ClubController::class);

    // Nested club routes: each {season}, {event}, {race} and {pigeon} must belong to the one before it (404 otherwise)
    Route::scopeBindings()->group(function () {
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
        Route::post('clubs/{club}/seasons/{season}/entries/bulk', [ClubSeasonController::class, 'addBulkEntries'])
            ->name('clubs.seasons.entries.bulk');
        Route::delete('clubs/{club}/seasons/{season}/entries/{pigeon}', [ClubSeasonController::class, 'removeEntry'])
            ->name('clubs.seasons.entries.destroy');
        Route::patch('clubs/{club}/seasons/{season}/entries/{pigeon}', [ClubSeasonController::class, 'updateEntry'])
            ->name('clubs.seasons.entries.update');

        // Club Season Events
        Route::post('clubs/{club}/seasons/{season}/events', [ClubSeasonEventController::class, 'store'])
            ->name('clubs.seasons.events.store');
        Route::get('clubs/{club}/seasons/{season}/events/{event}', [ClubSeasonEventController::class, 'show'])
            ->name('clubs.seasons.events.show');
        Route::patch('clubs/{club}/seasons/{season}/events/{event}', [ClubSeasonEventController::class, 'update'])
            ->name('clubs.seasons.events.update');
        Route::delete('clubs/{club}/seasons/{season}/events/{event}', [ClubSeasonEventController::class, 'destroy'])
            ->name('clubs.seasons.events.destroy');
        Route::post('clubs/{club}/seasons/{season}/events/{event}/entries', [ClubSeasonEventController::class, 'addEntry'])
            ->name('clubs.seasons.events.entries.store');
        Route::post('clubs/{club}/seasons/{season}/events/{event}/entries/bulk', [ClubSeasonEventController::class, 'addBulkEntries'])
            ->name('clubs.seasons.events.entries.bulk');
        Route::delete('clubs/{club}/seasons/{season}/events/{event}/entries/{pigeon}', [ClubSeasonEventController::class, 'removeEntry'])
            ->name('clubs.seasons.events.entries.destroy');

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
});

require __DIR__.'/settings.php';
require __DIR__.'/storage.php';
require __DIR__.'/account.php';
require __DIR__.'/admin.php';
