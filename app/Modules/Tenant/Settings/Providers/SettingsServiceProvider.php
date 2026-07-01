<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Providers;

use App\Modules\Tenant\Settings\Livewire\BrandingSettings;
use App\Modules\Tenant\Settings\Livewire\LocalizationSettings;
use App\Modules\Tenant\Settings\Livewire\SmtpSettings;
use App\Modules\Tenant\Settings\Livewire\UsageOverview;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'settings-tenant');

        Livewire::component('tenant-branding-settings', BrandingSettings::class);
        Livewire::component('tenant-localization-settings', LocalizationSettings::class);
        Livewire::component('tenant-smtp-settings', SmtpSettings::class);
        Livewire::component('tenant-usage-overview', UsageOverview::class);
    }
}
