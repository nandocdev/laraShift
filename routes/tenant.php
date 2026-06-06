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
    Route::get('/', \App\Modules\Central\Landings\Http\Controllers\ServeTenantLandingController::class)->name('tenant.home');

    Route::get('/auth/login', \App\Modules\Tenant\Identity\Livewire\Login::class)->name('login');
    Route::get('/auth/2fa/verify', \App\Modules\Tenant\Identity\Livewire\LoginChallenge::class)->name('login.challenge');
    Route::get('/auth/invitations/{token}/accept', \App\Modules\Tenant\Identity\Livewire\AcceptInvitation::class)->name('tenant.invitations.accept');

    // Support & Impersonation
    Route::get('/support/auth', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'authenticate'])->name('tenant.support.auth');
    Route::post('/support/logout', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'logout'])->name('tenant.support.logout');

    Route::middleware([
        'auth', 
        \App\Modules\Tenant\Identity\Http\Middleware\EnforceTenantMfa::class,
        \App\Modules\Tenant\Identity\Http\Middleware\EnsureUserIsActive::class,
        \App\Modules\Tenant\Identity\Http\Middleware\EnsureUserBelongsToTenant::class
    ])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
        
        Route::get('/team/members', \App\Modules\Tenant\Identity\Livewire\TeamManagement::class)->name('tenant.team.index');
        Route::get('/settings/roles', \App\Modules\Tenant\Identity\Livewire\RoleManagement::class)->name('tenant.roles.index');
        Route::get('/settings/api-keys', \App\Modules\Tenant\Identity\Livewire\ManageApiKeys::class)->name('tenant.api-keys.index');
        Route::get('/settings/branding', \App\Modules\Tenant\Settings\Livewire\BrandingSettings::class)->name('tenant.settings.branding');
        Route::get('/settings/localization', \App\Modules\Tenant\Settings\Livewire\LocalizationSettings::class)->name('tenant.settings.localization');
        Route::get('/settings/smtp', \App\Modules\Tenant\Settings\Livewire\SmtpSettings::class)->name('tenant.settings.smtp');
        Route::get('/settings/security/2fa', \App\Modules\Tenant\Identity\Livewire\TwoFactorEnrollment::class)->name('tenant.settings.security.2fa');
        Route::get('/audit', \App\Modules\Tenant\Audit\Livewire\AuditLogViewer::class)->name('tenant.audit.index');
        Route::get('/audit/download', function (Illuminate\Http\Request $request) {
            if (! $request->hasValidSignature()) {
                abort(403);
            }
            return Illuminate\Support\Facades\Storage::disk('private')->download($request->path);
        })->name('tenant.audit.download');
        
        Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
        Route::get('/billing/plans', \App\Modules\Central\Billing\Livewire\SelectPlan::class)->name('tenant.billing.plans');
        Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update-payment');

        // Landing Builder
        Route::get('/landings/{landing}/builder', \App\Modules\Central\Landings\Livewire\LandingBuilder::class)->name('tenant.landings.builder');
    });
});
