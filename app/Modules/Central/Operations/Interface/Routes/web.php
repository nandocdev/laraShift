<?php

declare(strict_types=1);

use App\Modules\Central\Operations\Interface\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/health', HealthCheckController::class)->name('central.health');
});

// Public probe for orchestrator (K8s/Railway) — no auth, throttled, IP allowlist enforced in controller
Route::middleware(['web', 'throttle:60,1'])->group(function () {
    Route::get('/up/central', HealthCheckController::class)->name('central.health.public');
    Route::get('/up', HealthCheckController::class)->name('health.up');
});
