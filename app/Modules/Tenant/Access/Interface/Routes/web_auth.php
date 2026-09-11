<?php

declare(strict_types=1);

use App\Modules\Tenant\Access\Interface\Livewire\ManageApiKeys;
use App\Modules\Tenant\Access\Interface\Livewire\RoleManagement;
use App\Modules\Tenant\Access\Interface\Livewire\SsoSettings;
use App\Modules\Tenant\Access\Interface\Livewire\TenantSessions;
use App\Modules\Tenant\Access\Interface\Livewire\TwoFactorEnrollment;
use Illuminate\Support\Facades\Route;

Route::get('/settings/roles', RoleManagement::class)->name('tenant.roles.index')->middleware('can:roles:manage');
Route::get('/settings/api-keys', ManageApiKeys::class)->name('tenant.api-keys.index')->middleware('feature:api_access');
Route::get('/settings/security/2fa', TwoFactorEnrollment::class)->name('tenant.settings.security.2fa');
Route::get('/settings/security/sso', SsoSettings::class)->name('tenant.settings.security.sso')->middleware('can:settings:manage');
Route::get('/settings/security/sessions', TenantSessions::class)->name('tenant.settings.security.sessions');

// Starter-kit user profile pages: tenant-scoped (tenancy comes from the
// parent group in routes/tenant.php). Must never render without tenancy:
// the tenant layout calls TenantFeatureResolver with tenant().
Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
Route::livewire('settings/appearance', 'pages::settings.appearance')->middleware('verified')->name('appearance.edit');
Route::livewire('settings/security', 'pages::settings.security')
    ->middleware(['verified', 'password.confirm'])
    ->name('security.edit');
