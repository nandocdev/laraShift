<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Providers;

use App\Modules\Central\Analytics\Livewire\AnalyticsDashboard;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'analytics');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/analytics', AnalyticsDashboard::class)->name('central.analytics.dashboard');
                });
        });

        Livewire::component('analytics-dashboard', AnalyticsDashboard::class);
    }
}
