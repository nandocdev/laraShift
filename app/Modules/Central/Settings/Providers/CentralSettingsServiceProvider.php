<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CentralSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'settings');
        
        $this->app->booted(function () {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    \Illuminate\Support\Facades\Route::get('/central/settings/branding', \App\Modules\Central\Settings\Livewire\PlatformBranding::class)
                        ->name('central.settings.branding');
                });
        });

        Livewire::component('central-platform-branding', \App\Modules\Central\Settings\Livewire\PlatformBranding::class);
    }
}
