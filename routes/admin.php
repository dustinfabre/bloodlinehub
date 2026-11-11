<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\PaymentAccountController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\NoIndex;
use Illuminate\Support\Facades\Route;

// Admin dashboard. Non-admins get a 404 (EnsureUserIsAdmin). Grant access with `php artisan admin:grant {email}`.
Route::middleware(['auth', 'verified', 'admin', NoIndex::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->name('dashboard');

        // Bank transfers waiting for review
        Route::get('payments', [PaymentController::class, 'index'])
            ->name('payments.index');
        Route::post('payments/{payment}/approve', [PaymentController::class, 'approve'])
            ->name('payments.approve');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])
            ->name('payments.reject');

        // Feedback inbox
        Route::get('inbox', [InboxController::class, 'index'])
            ->name('inbox.index');
        Route::patch('inbox/{feedback}', [InboxController::class, 'update'])
            ->name('inbox.update');
        Route::delete('inbox/{feedback}', [InboxController::class, 'destroy'])
            ->name('inbox.destroy');
        Route::get('inbox/{feedback}/screenshot', [InboxController::class, 'screenshot'])
            ->name('inbox.screenshot');

        // Accounts and Premium
        Route::get('users', [UserController::class, 'index'])
            ->name('users.index');
        Route::patch('users/{user}/premium', [UserController::class, 'updatePremium'])
            ->name('users.premium');

        // Where payments go and what they buy. Updates are POST because they can carry a QR code upload.
        Route::get('bank-accounts', [PaymentAccountController::class, 'index'])
            ->name('bank-accounts.index');
        Route::post('bank-accounts', [PaymentAccountController::class, 'store'])
            ->name('bank-accounts.store');
        Route::post('bank-accounts/{paymentAccount}', [PaymentAccountController::class, 'update'])
            ->name('bank-accounts.update');
        Route::delete('bank-accounts/{paymentAccount}', [PaymentAccountController::class, 'destroy'])
            ->name('bank-accounts.destroy');

        // Free and Premium: Premium's prices and free trial
        Route::get('plans', [PlanController::class, 'index'])
            ->name('plans.index');
        Route::patch('plans', [PlanController::class, 'update'])
            ->name('plans.update');
    });
