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
    })->name('tenant.home');

    Route::get('/login', \App\Modules\Tenant\Identity\Livewire\Login::class)->name('login');

    // Support & Impersonation
    Route::get('/support/auth', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'authenticate'])->name('tenant.support.auth');
    Route::post('/support/logout', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'logout'])->name('tenant.support.logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('/team', \App\Modules\Tenant\Identity\Livewire\TeamManagement::class)->name('tenant.team.index');
        Route::get('/settings/api-keys', \App\Modules\Tenant\Identity\Livewire\ManageApiKeys::class)->name('tenant.api-keys.index');
        Route::get('/settings/branding', \App\Modules\Tenant\Settings\Livewire\BrandingSettings::class)->name('tenant.settings.branding');
        Route::get('/audit', \App\Modules\Tenant\Audit\Livewire\AuditLogViewer::class)->name('tenant.audit.index');
        Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
        Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update-payment');
    });
});
