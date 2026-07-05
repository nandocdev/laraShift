<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ExperienceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Share view namespace 'settings-tenant' to preserve view resolution compatibility
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'settings-tenant');
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views/landings', 'landings');
        
        \Illuminate\Support\Facades\Blade::component('landings::layouts.landing', 'landing-layout');
        Livewire::component('landing-builder', \App\Modules\Tenant\Experience\Interface\Livewire\LandingBuilder::class);
        
        Livewire::component('tenant-branding-settings', \App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings::class);
        Livewire::component('tenant-localization-settings', \App\Modules\Tenant\Experience\Interface\Livewire\LocalizationSettings::class);
        Livewire::component('tenant-usage-overview', \App\Modules\Tenant\Experience\Interface\Livewire\UsageOverview::class);
    }
}
