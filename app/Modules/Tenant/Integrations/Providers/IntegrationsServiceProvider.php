<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Share view namespace 'settings-tenant' to preserve view resolution compatibility
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'settings-tenant');
        
        Livewire::component('tenant-smtp-settings', \App\Modules\Tenant\Integrations\Interface\Livewire\SmtpSettings::class);
    }
}
