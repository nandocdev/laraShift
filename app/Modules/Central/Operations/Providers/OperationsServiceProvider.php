<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Providers;

use App\Modules\Central\Operations\Infrastructure\Console\HorizonUpdateCommand;
use App\Modules\Central\Operations\Interface\Livewire\PlatformBranding;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'settings');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/web.php');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/settings/branding', PlatformBranding::class)
                        ->name('central.settings.branding');
                });
        });

        Livewire::component('central-platform-branding', PlatformBranding::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                HorizonUpdateCommand::class,
            ]);
        }
    }
}
