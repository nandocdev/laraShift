<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\Providers;

use Illuminate\Support\ServiceProvider;

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
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'landings');
        
        \Illuminate\Support\Facades\Blade::component('landings::layouts.landing', 'landing-layout');

        \Livewire\Livewire::component('landing-builder', \App\Modules\Central\Landings\Livewire\LandingBuilder::class);
    }
}
