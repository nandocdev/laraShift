<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Providers;

use App\Modules\Central\Growth\Interface\Livewire\LandingPage;
use App\Modules\Central\Growth\Interface\Livewire\RegisterTenant;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class GrowthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'marketing');

        Livewire::component('marketing-landing-page', LandingPage::class);
        Livewire::component('marketing-register-tenant', RegisterTenant::class);
    }
}
