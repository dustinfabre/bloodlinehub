<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\FeedbackController;
use App\Support\PremiumFeature;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Plan & billing: pay for Premium by bank transfer and upload the receipt
    Route::get('billing', [BillingController::class, 'index'])
        ->name('billing.index');
    Route::post('billing/payments', [BillingController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('billing.payments.store');
    // Private files on R2, streamed only to who may see them (the payer and admins; any signed-in user for a QR)
    Route::get('billing/payments/{payment}/receipt', [BillingController::class, 'receipt'])
        ->name('billing.payments.receipt');
    Route::get('billing/accounts/{paymentAccount}/qr', [BillingController::class, 'qr'])
        ->name('billing.accounts.qr');

    // What a Premium feature does and how to get it, for buttons that are locked in the browser (e.g. Save image)
    Route::get('premium/{feature}', [BillingController::class, 'feature'])
        ->whereIn('feature', array_column(PremiumFeature::cases(), 'value'))
        ->name('premium.show');

    // Feedback page; messages land in the admin inbox
    Route::get('feedback', [FeedbackController::class, 'create'])
        ->name('feedback.create');
    Route::post('feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('feedback.store');
});
