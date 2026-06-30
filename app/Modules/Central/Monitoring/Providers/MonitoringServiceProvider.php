<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Providers;

use App\Modules\Central\Monitoring\Livewire\LogViewer;
use App\Modules\Central\Monitoring\Livewire\MonitoringDashboard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MonitoringServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'monitoring');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/monitoring', MonitoringDashboard::class)
                        ->name('central.monitoring.dashboard');
                    Route::get('/central/monitoring/logs', LogViewer::class)
                        ->name('central.monitoring.logs');
                });
        });

        Livewire::component('monitoring-dashboard', MonitoringDashboard::class);
        Livewire::component('log-viewer', LogViewer::class);
    }
}
