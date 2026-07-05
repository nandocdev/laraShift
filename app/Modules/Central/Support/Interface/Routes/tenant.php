<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/support/auth', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'authenticate'])->name('tenant.support.auth');
Route::post('/support/logout', [\App\Modules\Central\Support\Http\Controllers\TenantImpersonationController::class, 'logout'])->name('tenant.support.logout');
