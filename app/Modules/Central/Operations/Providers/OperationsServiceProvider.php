<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'settings');
        $this->loadRoutesFrom(__DIR__ . '/../Interface/Routes/web.php');
        
        $this->app->booted(function () {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    \Illuminate\Support\Facades\Route::get('/central/settings/branding', \App\Modules\Central\Operations\Interface\Livewire\PlatformBranding::class)
                        ->name('central.settings.branding');
                });
        });

        Livewire::component('central-platform-branding', \App\Modules\Central\Operations\Interface\Livewire\PlatformBranding::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Central\Operations\Infrastructure\Console\HorizonUpdateCommand::class,
            ]);
        }
    }
}
