<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Providers;

use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Listeners\CreateInitialAdminUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyInitialized;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 1. Listen for Tenant Provisioning to create first admin
        Event::listen(
            TenantProvisioned::class,
            CreateInitialAdminUser::class
        );

        // 2. Set the current tenant as the "Team" for permissions
        Event::listen(TenancyInitialized::class, function (TenancyInitialized $event) {
            setPermissionsTeamId($event->tenancy->tenant->getTenantKey());
        });

        // 3. Register components and routes
        $this->loadViewsFrom(__DIR__ . '/../UI', 'identity');

        \Livewire\Livewire::component('tenant-login', \App\Modules\Tenant\Identity\Livewire\Login::class);
        \Livewire\Livewire::component('tenant-team-management', \App\Modules\Tenant\Identity\Livewire\TeamManagement::class);
    }
}
