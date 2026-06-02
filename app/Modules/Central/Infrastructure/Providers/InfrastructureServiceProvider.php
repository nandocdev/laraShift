<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class InfrastructureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            Route::get('/central/health', \App\Modules\Central\Infrastructure\Http\Controllers\HealthCheckController::class)
                ->name('central.health');
        });
    }
}
