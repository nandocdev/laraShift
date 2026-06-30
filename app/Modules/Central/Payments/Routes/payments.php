<?php

declare(strict_types=1);

use App\Modules\Central\Payments\Http\Controllers\CheckoutController;
use App\Modules\Central\Payments\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Payment Routes
|--------------------------------------------------------------------------
|
| Webhook route must be OUTSIDE the tenant middleware stack because:
|   1. The gateway posts to it without a session
|   2. Tenant resolution happens via the payload or a URL segment, not auth
|
| Checkout routes sit inside the standard tenant middleware.
|
*/

// ── Webhook (no auth, no tenant middleware — raw HTTP) ───────────────────────
Route::post('/webhooks/clave', [WebhookController::class, 'handle'])
    ->name('payments.webhooks.clave')
    ->middleware('throttle:webhooks')
    ->withoutMiddleware(['web', 'auth', 'tenant']);

Route::post('/webhooks/dlocal', [WebhookController::class, 'handle'])
    ->name('payments.webhooks.dlocal')
    ->middleware('throttle:webhooks')
    ->withoutMiddleware(['web', 'auth', 'tenant']);

Route::post('/webhooks/dlocal/payout', [WebhookController::class, 'handle'])
    ->name('payments.webhooks.dlocal_payout')
    ->middleware('throttle:webhooks')
    ->withoutMiddleware(['web', 'auth', 'tenant']);

// ── Tenant-scoped checkout ───────────────────────────────────────────────────
Route::middleware(['web', 'tenant', 'auth', 'verified'])
    ->prefix('payments')
    ->name('payments.')
    ->group(function (): void {
        Route::post('/checkout/initiate', [CheckoutController::class, 'initiate'])
            ->name('checkout.initiate');
    });
