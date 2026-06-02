<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Illuminate\Support\Facades\Route;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'settings-tenant');
        
        Livewire::component('tenant-branding-settings', \App\Modules\Tenant\Settings\Livewire\BrandingSettings::class);
        Livewire::component('tenant-localization-settings', \App\Modules\Tenant\Settings\Livewire\LocalizationSettings::class);
        Livewire::component('tenant-smtp-settings', \App\Modules\Tenant\Settings\Livewire\SmtpSettings::class);
        Livewire::component('tenant-usage-overview', \App\Modules\Tenant\Settings\Livewire\UsageOverview::class);
    }
}
