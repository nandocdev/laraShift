<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Providers;

use App\Modules\Tenant\Compliance\Application\Listeners\TenantAuthAuditSubscriber;
use App\Modules\Tenant\Compliance\Interface\Livewire\AuditLogViewer;
use App\Modules\Tenant\Compliance\Interface\Livewire\DataExport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ComplianceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {

        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'compliance');

        Livewire::component('tenant-audit-viewer', AuditLogViewer::class);
        Livewire::component('tenant-data-export', DataExport::class);

        Event::subscribe(TenantAuthAuditSubscriber::class);
    }
}
