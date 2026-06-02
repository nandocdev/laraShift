<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'audit');
        
        Livewire::component('tenant-audit-viewer', \App\Modules\Tenant\Audit\Livewire\AuditLogViewer::class);

        // Register Subscribers
        \Illuminate\Support\Facades\Event::subscribe(\App\Modules\Tenant\Audit\Listeners\TenantAuthAuditSubscriber::class);
    }
}
