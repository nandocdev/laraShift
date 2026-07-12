<?php

declare(strict_types=1);

use App\Modules\Tenant\Access\Interface\Livewire\ManageApiKeys;
use App\Modules\Tenant\Access\Interface\Livewire\RoleManagement;
use App\Modules\Tenant\Access\Interface\Livewire\TwoFactorEnrollment;
use Illuminate\Support\Facades\Route;

Route::get('/settings/roles', RoleManagement::class)->name('tenant.roles.index');
Route::get('/settings/api-keys', ManageApiKeys::class)->name('tenant.api-keys.index');
Route::get('/settings/security/2fa', TwoFactorEnrollment::class)->name('tenant.settings.security.2fa');
