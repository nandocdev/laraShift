<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MarketingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'marketing');

        Livewire::component('marketing-landing-page', \App\Modules\Central\Marketing\Livewire\LandingPage::class);
    }
}
