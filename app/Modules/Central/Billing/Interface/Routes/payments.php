<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Interface\Http\Controllers\CheckoutController;
use App\Modules\Central\Billing\Interface\Http\Controllers\DlocalWebhookController;
use App\Modules\Central\Billing\Interface\Http\Controllers\PaguelofacilCallbackController;
use App\Modules\Central\Billing\Interface\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing routes
|--------------------------------------------------------------------------
|
| The webhook route runs with no session, no auth and no tenant middleware:
| the gateway posts raw HTTP and the tenant is resolved from the payload.
*/

// Gateway → us: raw webhook (verify sync, 401 without touching DB).
Route::post('/webhooks/clave', [WebhookController::class, 'handle'])
    ->name('payments.webhooks.clave')
    ->middleware('throttle:30,1')
    ->withoutMiddleware(['web', 'auth', 'tenant']);

Route::post('/webhooks/dlocal', [DlocalWebhookController::class, 'handle'])
    ->name('payments.webhooks.dlocal')
    ->middleware('throttle:30,1')
    ->withoutMiddleware(['web', 'auth', 'tenant']);

// Gateway → browser → us: return URL. UX-only, never mutates state.
Route::get('/billing/clave/callback', [PaguelofacilCallbackController::class, 'handleReturn'])
    ->name('payments.clave.callback')
    ->middleware('web');

// Tenant-scoped checkout initiation (requires tenant middleware upstream).
Route::middleware(['web', 'tenant', 'auth', 'verified'])
    ->prefix('payments')
    ->name('payments.')
    ->group(function (): void {
        Route::post('/checkout/initiate', [CheckoutController::class, 'initiate'])
            ->name('checkout.initiate');
    });
