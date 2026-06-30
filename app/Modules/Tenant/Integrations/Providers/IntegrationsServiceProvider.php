<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Providers;

use App\Modules\Tenant\Integrations\Livewire\ManageWebhooks;
use App\Modules\Tenant\Integrations\Livewire\WebhookDeliveryLog;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'integrations');

        Livewire::component('tenant-manage-webhooks', ManageWebhooks::class);
        Livewire::component('tenant-webhook-delivery-log', WebhookDeliveryLog::class);
    }
}
