<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Providers;

use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Listeners\CreateInitialAdminUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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
        \Livewire\Livewire::component('tenant-accept-invitation', \App\Modules\Tenant\Identity\Livewire\AcceptInvitation::class);
        \Livewire\Livewire::component('tenant-login-challenge', \App\Modules\Tenant\Identity\Livewire\LoginChallenge::class);
        \Livewire\Livewire::component('tenant-2fa-enrollment', \App\Modules\Tenant\Identity\Livewire\TwoFactorEnrollment::class);
        \Livewire\Livewire::component('tenant-team-management', \App\Modules\Tenant\Identity\Livewire\TeamManagement::class);
        Livewire::component('tenant-role-management', \App\Modules\Tenant\Identity\Livewire\RoleManagement::class);
        Livewire::component('tenant-manage-api-keys', \App\Modules\Tenant\Identity\Livewire\ManageApiKeys::class);
        Livewire::component('tenant-notification-center', \App\Modules\Tenant\Identity\Livewire\NotificationCenter::class);
        Livewire::component('tenant-data-export', \App\Modules\Tenant\Identity\Livewire\DataExport::class);

        // 4. Register Event Subscriber
        Event::subscribe(\App\Modules\Tenant\Identity\Listeners\TenantIdentityEventSubscriber::class);
    }
}
