<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Providers;

use App\Modules\Central\Settings\Interface\Livewire\PlatformBranding;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'settings');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/settings/branding', PlatformBranding::class)
                        ->name('central.settings.branding');
                });
        });

        Livewire::component('central-platform-branding', PlatformBranding::class);
    }
}
