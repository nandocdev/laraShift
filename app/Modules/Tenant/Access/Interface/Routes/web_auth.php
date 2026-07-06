<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/settings/roles', \App\Modules\Tenant\Access\Interface\Livewire\RoleManagement::class)->name('tenant.roles.index');
Route::get('/settings/api-keys', \App\Modules\Tenant\Access\Interface\Livewire\ManageApiKeys::class)->name('tenant.api-keys.index');
Route::get('/settings/export', \App\Modules\Tenant\Access\Interface\Livewire\DataExport::class)->name('tenant.settings.export');
Route::get('/settings/security/2fa', \App\Modules\Tenant\Access\Interface\Livewire\TwoFactorEnrollment::class)->name('tenant.settings.security.2fa');
