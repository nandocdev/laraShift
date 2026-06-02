<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Livewire\ManageBilling;
use App\Modules\Central\Billing\Livewire\UpdatePaymentMethod;
use App\Modules\Shared\Tenancy\Http\Middleware\ApplyTenantRateLimits;
use App\Modules\Shared\Tenancy\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantIsActive::class,
    ApplyTenantRateLimits::class,
    \App\Modules\Central\Support\Http\Middleware\AuditImpersonationActions::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });

    // Support & Impersonation
    Route::get('/support/auth', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'authenticate'])->name('tenant.support.auth');
    Route::post('/support/logout', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'logout'])->name('tenant.support.logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
        Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update-payment');
    });
});
