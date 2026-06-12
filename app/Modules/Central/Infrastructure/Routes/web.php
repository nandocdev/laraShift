<?php

declare(strict_types=1);

use App\Modules\Central\Infrastructure\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:central'])->group(function () {
    Route::get('/central/health', HealthCheckController::class)->name('central.health');
});
