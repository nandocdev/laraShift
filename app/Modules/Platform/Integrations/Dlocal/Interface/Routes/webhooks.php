<?php

declare(strict_types=1);

use App\Modules\Platform\Integrations\Dlocal\Interface\Http\Controllers\DlocalCallbackController;
use App\Modules\Platform\Integrations\Dlocal\Interface\Http\Controllers\DlocalWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/dlocal', [DlocalWebhookController::class, 'handle'])
    ->name('payments.webhooks.dlocal')
    ->withoutMiddleware(['web', 'auth', 'tenant']);

// Browser callback for the REDIRECT flow: dLocal sends the customer here
// (GET or POST) with paymentId + status, and we bounce to the tenant's
// billing success/cancel page.
Route::any('/central/billing/dlocal/callback', [DlocalCallbackController::class, 'handle'])
    ->name('central.billing.dlocal.callback')
    ->withoutMiddleware(['web', 'auth', 'tenant']);
