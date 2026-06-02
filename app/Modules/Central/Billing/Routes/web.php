<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Http\Controllers\StripeWebhookController;
use App\Modules\Central\Billing\Livewire\SubscriptionList;
use Illuminate\Support\Facades\Route;

Route::post('/central/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])->name('central.billing.webhook.stripe');

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/billing/subscriptions', SubscriptionList::class)->name('central.billing.subscriptions');
    
    // Placeholder routes for checkout success/cancel
    Route::get('/central/billing/success/{tenant}', function () {
        return 'Success';
    })->name('central.billing.success');

    Route::get('/central/billing/cancel/{tenant}', function () {
        return 'Cancelled';
    })->name('central.billing.cancel');
});
