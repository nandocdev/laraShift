<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Providers;

use App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings;
use App\Modules\Tenant\Experience\Interface\Livewire\LandingBuilder;
use App\Modules\Tenant\Experience\Interface\Livewire\LocalizationSettings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ExperienceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Share view namespace 'settings-tenant' to preserve view resolution compatibility
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'settings-tenant');
        $this->loadViewsFrom(__DIR__.'/../Interface/Views/landings', 'landings');

        Blade::component('landings::layouts.landing', 'landing-layout');
        Livewire::component('landing-builder', LandingBuilder::class);

        Livewire::component('tenant-branding-settings', BrandingSettings::class);
        Livewire::component('tenant-localization-settings', LocalizationSettings::class);
    }
}
