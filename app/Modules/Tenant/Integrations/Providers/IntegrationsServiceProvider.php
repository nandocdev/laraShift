<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Providers;

use App\Modules\Platform\Events\TenantApiKeyCreated;
use App\Modules\Tenant\Integrations\Application\Listeners\DispatchApiKeyCreatedWebhook;
use App\Modules\Tenant\Integrations\Interface\Livewire\ManageWebhooks;
use App\Modules\Tenant\Integrations\Interface\Livewire\SmtpSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Share view namespace 'settings-tenant' to preserve view resolution compatibility
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'settings-tenant');

        Livewire::component('tenant-smtp-settings', SmtpSettings::class);
        Livewire::component('tenant-manage-webhooks', ManageWebhooks::class);

        Event::listen(TenantApiKeyCreated::class, DispatchApiKeyCreatedWebhook::class);
    }
}
