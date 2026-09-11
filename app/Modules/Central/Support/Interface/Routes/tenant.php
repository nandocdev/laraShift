<?php

declare(strict_types=1);

use App\Modules\Central\Support\Http\Controllers\TenantImpersonationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:5,1')->group(function () {
    Route::get('/support/auth', [TenantImpersonationController::class, 'authenticate'])->name('tenant.support.auth');
    Route::post('/support/logout', [TenantImpersonationController::class, 'logout'])->name('tenant.support.logout');
});
