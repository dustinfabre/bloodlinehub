<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceController;
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
});

require __DIR__.'/settings.php';
