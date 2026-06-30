<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Providers;

use App\Modules\Central\Landings\Livewire\LandingBuilder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LandingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'landings');

        Blade::component('landings::layouts.landing', 'landing-layout');

        Livewire::component('landing-builder', LandingBuilder::class);
    }
}
