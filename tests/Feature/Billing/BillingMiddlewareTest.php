<?php

declare(strict_types=1);

use App\Modules\Platform\Tenancy\Interface\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;

function billingMatrixRoutes(): void
{
    Route::get('/mw-billing', fn () => 'billing-ok')
        ->name('tenant.billing.manage')
        ->middleware(EnsureTenantIsActive::class);

    Route::get('/mw-checkout', fn () => 'checkout-ok')
        ->name('payments.checkout.initiate')
        ->middleware(EnsureTenantIsActive::class);

    Route::get('/mw-dashboard', fn () => 'dashboard-ok')
        ->middleware(EnsureTenantIsActive::class);
}

it('lets pending_payment tenants use billing and checkout only', function () {
    billingMatrixRoutes();
    $tenant = claveTestTenant('mw-pending');
    $tenant->update(['status' => 'pending_payment']);
    tenancy()->initialize($tenant);

    try {
        $this->get('/mw-billing')->assertOk();
        $this->get('/mw-checkout')->assertOk();
        $this->get('/mw-dashboard')->assertStatus(402);
    } finally {
        tenancy()->end();
    }
});

it('lets suspended tenants use billing and login only', function () {
    billingMatrixRoutes();
    $tenant = claveTestTenant('mw-suspended');
    $tenant->update(['status' => 'suspended']);
    tenancy()->initialize($tenant);

    try {
        $this->get('/mw-billing')->assertOk();
        $this->get('/mw-dashboard')->assertForbidden();
    } finally {
        tenancy()->end();
    }
});
