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
    Route::post('/auth/login', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::post('/auth/logout', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/auth/2fa/verify', \App\Modules\Tenant\Identity\Livewire\LoginChallenge::class)->name('two-factor.login');
    Route::post('/auth/2fa/verify', [\Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController::class, 'store'])->name('two-factor.login.store');

    Route::post('/auth/forgot-password', [\Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/auth/forgot-password', fn () => view('pages::auth.forgot-password'))->name('password.request');
    Route::post('/auth/reset-password', [\Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'store'])->name('password.update');
    Route::get('/auth/reset-password/{token}', fn ($token) => view('pages::auth.reset-password', ['token' => $token]))->name('password.reset');

    Route::get('/auth/register', fn () => view('pages::auth.register'))->name('register');
    Route::post('/auth/register', [\Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/auth/verify-email', fn () => view('pages::auth.verify-email'))->name('verification.notice');
    Route::get('/auth/verify-email/{id}/{hash}', [\Laravel\Fortify\Http\Controllers\VerifyEmailController::class, '__invoke'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/auth/email/verification-notification', [\Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController::class, 'store'])->middleware(['throttle:6,1'])->name('verification.send');

    Route::get('/auth/confirm-password', fn () => view('pages::auth.confirm-password'))->name('password.confirm');
    Route::post('/auth/confirm-password', [\Laravel\Fortify\Http\Controllers\ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');

    Route::post('/auth/passkeys/login', [\Laravel\Passkeys\Http\Controllers\PasskeyLoginController::class, 'store'])->name('passkey.login');
    Route::get('/auth/passkeys/login/options', [\Laravel\Passkeys\Http\Controllers\PasskeyLoginController::class, 'index'])->name('passkey.login-options');
    Route::post('/auth/passkeys/register', [\Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController::class, 'store'])->name('passkey.register');
    Route::get('/auth/passkeys/register/options', [\Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController::class, 'index'])->name('passkey.register-options');
    Route::post('/auth/passkeys/confirm', [\Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController::class, 'store'])->name('passkey.confirm');
    Route::get('/auth/passkeys/confirm/options', [\Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController::class, 'index'])->name('passkey.confirm-options');

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
        Route::get('/settings/export', \App\Modules\Tenant\Identity\Livewire\DataExport::class)->name('tenant.settings.export');
        Route::get('/settings/security/2fa', \App\Modules\Tenant\Identity\Livewire\TwoFactorEnrollment::class)->name('tenant.settings.security.2fa');
        Route::get('/audit', \App\Modules\Tenant\Audit\Livewire\AuditLogViewer::class)->name('tenant.audit.index');
        Route::get('/audit/download', \App\Modules\Tenant\Audit\Http\Controllers\AuditDownloadController::class)->name('tenant.audit.download');

        Route::get('/payouts', \App\Modules\Central\Payments\Livewire\PayoutRequests::class)->name('tenant.payouts.index');
        Route::get('/settings/payouts', \App\Modules\Central\Payments\Livewire\PayoutSettings::class)->name('tenant.settings.payouts');

        Route::get('/data/download', \App\Modules\Tenant\Audit\Http\Controllers\AuditDownloadController::class)->name('tenant.data.download');
        
        Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
        Route::get('/billing/plans', \App\Modules\Central\Billing\Livewire\SelectPlan::class)->name('tenant.billing.plans');
        Route::get('/billing/checkout/hosted/{tenant_uuid}/{plan_uuid}', \App\Modules\Central\Billing\Livewire\HostedCheckout::class)->name('tenant.billing.checkout.hosted');
        Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update-payment');

        Route::get('/billing/success', function () {
            return view('billing::pages.success'); // Assuming I create this or use a generic one
        })->name('tenant.billing.success');

        Route::get('/billing/cancel', function () {
            return view('billing::pages.cancel');
        })->name('tenant.billing.cancel');

        // Landing Builder
        Route::get('/landings/{landing}/builder', \App\Modules\Central\Landings\Livewire\LandingBuilder::class)->name('tenant.landings.builder');
    });
});
