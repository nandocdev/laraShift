<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Providers;

use App\Modules\Tenant\Audit\Listeners\TenantAuthAuditSubscriber;
use App\Modules\Tenant\Audit\Livewire\AuditLogViewer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'audit');

        Livewire::component('tenant-audit-viewer', AuditLogViewer::class);

        // Register Subscribers
        Event::subscribe(TenantAuthAuditSubscriber::class);
    }
}
