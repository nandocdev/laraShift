<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'workspace');

        Livewire::component('tenant-team-management', \App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement::class);
        Livewire::component('tenant-notification-center', \App\Modules\Tenant\Workspace\Interface\Livewire\NotificationCenter::class);
        Livewire::component('tenant-usage-overview', \App\Modules\Tenant\Workspace\Interface\Livewire\UsageOverview::class);
    }
}
