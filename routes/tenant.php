<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Livewire\ManageBilling;
use App\Modules\Central\Billing\Livewire\SelectPlan;
use App\Modules\Central\Billing\Livewire\UpdatePaymentMethod;
use App\Modules\Central\Landings\Http\Controllers\ServeTenantLandingController;
use App\Modules\Central\Landings\Livewire\LandingBuilder;
use App\Modules\Central\Payments\Livewire\PayoutRequests;
use App\Modules\Central\Payments\Livewire\PayoutSettings;
use App\Modules\Central\Support\Http\Controllers\TenantImpersonationController;
use App\Modules\Central\Support\Http\Middleware\AuditImpersonationActions;
use App\Modules\Shared\Tenancy\Http\Middleware\ApplyTenantRateLimits;
use App\Modules\Shared\Tenancy\Http\Middleware\EnsureTenantIsActive;
use App\Modules\Tenant\Audit\Http\Controllers\AuditDownloadController;
use App\Modules\Tenant\Audit\Livewire\AuditLogViewer;
use App\Modules\Tenant\DataManagement\Livewire\ManageBackups;
use App\Modules\Tenant\DataManagement\Livewire\ManageDataImports;
use App\Modules\Tenant\DataManagement\Livewire\RetentionSettings;
use App\Modules\Tenant\Identity\Http\Middleware\EnforceTenantMfa;
use App\Modules\Tenant\Identity\Http\Middleware\EnsureUserBelongsToTenant;
use App\Modules\Tenant\Identity\Http\Middleware\EnsureUserIsActive;
use App\Modules\Tenant\Identity\Livewire\AcceptInvitation;
use App\Modules\Tenant\Identity\Livewire\DataExport;
use App\Modules\Tenant\Identity\Livewire\Login;
use App\Modules\Tenant\Identity\Livewire\LoginChallenge;
use App\Modules\Tenant\Identity\Livewire\ManageApiKeys;
use App\Modules\Tenant\Identity\Livewire\RoleManagement;
use App\Modules\Tenant\Identity\Livewire\TeamManagement;
use App\Modules\Tenant\Identity\Livewire\TwoFactorEnrollment;
use App\Modules\Tenant\Integrations\Livewire\ManageWebhooks;
use App\Modules\Tenant\Integrations\Livewire\WebhookDeliveryLog;
use App\Modules\Tenant\Notifications\Livewire\ManageNotificationTemplates;
use App\Modules\Tenant\Notifications\Livewire\NotificationPreferences;
use App\Modules\Tenant\Settings\Livewire\BrandingSettings;
use App\Modules\Tenant\Settings\Livewire\LocalizationSettings;
use App\Modules\Tenant\Settings\Livewire\SmtpSettings;
use App\Modules\Tenant\Settings\Livewire\UsageOverview;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;
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
    AuditImpersonationActions::class,
])->group(function () {
    Route::get('/', ServeTenantLandingController::class)->name('tenant.home');

    Route::get('/auth/login', Login::class)->name('login');
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/auth/2fa/verify', LoginChallenge::class)->name('two-factor.login');
    Route::post('/auth/2fa/verify', [TwoFactorAuthenticatedSessionController::class, 'store'])->name('two-factor.login.store');

    Route::post('/auth/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/auth/forgot-password', fn () => view('pages::auth.forgot-password'))->name('password.request');
    Route::post('/auth/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
    Route::get('/auth/reset-password/{token}', fn ($token) => view('pages::auth.reset-password', ['token' => $token]))->name('password.reset');

    Route::get('/auth/register', fn () => view('pages::auth.register'))->name('register');
    Route::post('/auth/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/auth/verify-email', fn () => view('pages::auth.verify-email'))->name('verification.notice');
    Route::get('/auth/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/auth/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware(['throttle:6,1'])->name('verification.send');

    Route::get('/auth/confirm-password', fn () => view('pages::auth.confirm-password'))->name('password.confirm');
    Route::post('/auth/confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');

    Route::post('/auth/passkeys/login', [PasskeyLoginController::class, 'store'])->name('passkey.login');
    Route::get('/auth/passkeys/login/options', [PasskeyLoginController::class, 'index'])->name('passkey.login-options');
    Route::post('/auth/passkeys/register', [PasskeyRegistrationController::class, 'store'])->name('passkey.register');
    Route::get('/auth/passkeys/register/options', [PasskeyRegistrationController::class, 'index'])->name('passkey.register-options');
    Route::post('/auth/passkeys/confirm', [PasskeyConfirmationController::class, 'store'])->name('passkey.confirm');
    Route::get('/auth/passkeys/confirm/options', [PasskeyConfirmationController::class, 'index'])->name('passkey.confirm-options');

    Route::get('/auth/invitations/{token}/accept', AcceptInvitation::class)->name('tenant.invitations.accept');

    // Support & Impersonation
    Route::get('/support/auth', [TenantImpersonationController::class, 'authenticate'])->name('tenant.support.auth');
    Route::post('/support/logout', [TenantImpersonationController::class, 'logout'])->name('tenant.support.logout');

    Route::middleware([
        'auth',
        EnforceTenantMfa::class,
        EnsureUserIsActive::class,
        EnsureUserBelongsToTenant::class,
    ])->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::get('/team/members', TeamManagement::class)->name('tenant.team.index');
        Route::get('/settings/roles', RoleManagement::class)->name('tenant.roles.index');
        Route::get('/settings/api-keys', ManageApiKeys::class)->name('tenant.api-keys.index');
        Route::get('/settings/branding', BrandingSettings::class)->name('tenant.settings.branding');
        Route::get('/settings/localization', LocalizationSettings::class)->name('tenant.settings.localization');
        Route::get('/settings/smtp', SmtpSettings::class)->name('tenant.settings.smtp');
        Route::get('/settings/export', DataExport::class)->name('tenant.settings.export');
        Route::get('/settings/security/2fa', TwoFactorEnrollment::class)->name('tenant.settings.security.2fa');
        Route::get('/settings/notifications/templates', ManageNotificationTemplates::class)->name('tenant.settings.notifications.templates');
        Route::get('/settings/notifications/preferences', NotificationPreferences::class)->name('tenant.settings.notifications.preferences');
        Route::get('/integrations/webhooks', ManageWebhooks::class)->name('tenant.integrations.webhooks');
        Route::get('/integrations/webhooks/delivery-log', WebhookDeliveryLog::class)->name('tenant.integrations.webhooks.delivery-log');
        Route::get('/data/import', ManageDataImports::class)->name('tenant.data.import');
        Route::get('/data/backups', ManageBackups::class)->name('tenant.data.backups');
        Route::get('/settings/retention', RetentionSettings::class)->name('tenant.settings.retention');
        Route::get('/usage', UsageOverview::class)->name('tenant.usage.index');
        Route::get('/audit', AuditLogViewer::class)->name('tenant.audit.index');
        Route::get('/audit/download', AuditDownloadController::class)->name('tenant.audit.download');

        Route::get('/payouts', PayoutRequests::class)->name('tenant.payouts.index');
        Route::get('/settings/payouts', PayoutSettings::class)->name('tenant.settings.payouts');

        Route::get('/data/download', AuditDownloadController::class)->name('tenant.data.download');

        Route::get('/billing', ManageBilling::class)->name('tenant.billing.manage');
        Route::get('/billing/plans', SelectPlan::class)->name('tenant.billing.plans');
        Route::get('/billing/checkout/hosted/{tenant_uuid}/{plan_uuid}', HostedCheckout::class)->name('tenant.billing.checkout.hosted');
        Route::get('/billing/update-payment', UpdatePaymentMethod::class)->name('tenant.billing.update-payment');

        Route::get('/billing/success', function () {
            return view('billing::pages.success'); // Assuming I create this or use a generic one
        })->name('tenant.billing.success');

        Route::get('/billing/cancel', function () {
            return view('billing::pages.cancel');
        })->name('tenant.billing.cancel');

        // Landing Builder
        Route::get('/landings/{landing}/builder', LandingBuilder::class)->name('tenant.landings.builder');
    });
});
