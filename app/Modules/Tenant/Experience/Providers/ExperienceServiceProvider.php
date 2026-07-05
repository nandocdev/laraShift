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
        
        Livewire::component('tenant-branding-settings', \App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings::class);
        Livewire::component('tenant-localization-settings', \App\Modules\Tenant\Experience\Interface\Livewire\LocalizationSettings::class);
        Livewire::component('tenant-usage-overview', \App\Modules\Tenant\Experience\Interface\Livewire\UsageOverview::class);
    }
}
