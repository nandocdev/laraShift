<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Providers;

use App\Modules\Tenant\Workspace\Interface\Livewire\NotificationCenter;
use App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement;
use App\Modules\Tenant\Workspace\Interface\Livewire\UsageOverview;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'workspace');

        Livewire::component('tenant-team-management', TeamManagement::class);
        Livewire::component('tenant-notification-center', NotificationCenter::class);
        Livewire::component('tenant-usage-overview', UsageOverview::class);
    }
}
