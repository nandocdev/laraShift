<?php

use App\Modules\Central\Provisioning\Livewire\CreateTenant;
use App\Modules\Central\Provisioning\Livewire\TenantList;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/tenants', TenantList::class)->name('central.provisioning.index');
    Route::get('/central/tenants/create', CreateTenant::class)->name('central.provisioning.create');
    Route::get('/central/tenants/{tenant}/edit', \App\Modules\Central\Provisioning\Livewire\ManageTenant::class)->name('central.provisioning.edit');
});
