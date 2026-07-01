<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Providers;

use App\Modules\Central\Settings\Livewire\PlatformBranding;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CentralSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'settings');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Livewire::component('central-platform-branding', PlatformBranding::class);
    }
}
