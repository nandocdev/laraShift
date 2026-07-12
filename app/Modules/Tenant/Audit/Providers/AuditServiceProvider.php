<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Providers;

use App\Modules\Tenant\Audit\Livewire\AuditLogViewer;
use App\Modules\Tenant\Audit\Interface\Livewire\DataExport;
use App\Modules\Tenant\Audit\Listeners\TenantAuthAuditSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'audit');
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'audit');

        Livewire::component('tenant-audit-viewer', AuditLogViewer::class);
        Livewire::component('tenant-data-export', DataExport::class);

        Event::subscribe(TenantAuthAuditSubscriber::class);
    }
}
