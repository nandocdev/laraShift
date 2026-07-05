<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Providers;

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Tenant\Access\Application\Listeners\CreateInitialAdminUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stancl\Tenancy\Events\TenancyInitialized;

class AccessServiceProvider extends ServiceProvider
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
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'identity');

        \Livewire\Livewire::component('tenant-login', \App\Modules\Tenant\Access\Interface\Livewire\Login::class);
        \Livewire\Livewire::component('tenant-accept-invitation', \App\Modules\Tenant\Access\Interface\Livewire\AcceptInvitation::class);
        \Livewire\Livewire::component('tenant-login-challenge', \App\Modules\Tenant\Access\Interface\Livewire\LoginChallenge::class);
        \Livewire\Livewire::component('tenant-2fa-enrollment', \App\Modules\Tenant\Access\Interface\Livewire\TwoFactorEnrollment::class);
        \Livewire\Livewire::component('tenant-team-management', \App\Modules\Tenant\Access\Interface\Livewire\TeamManagement::class);
        Livewire::component('tenant-role-management', \App\Modules\Tenant\Access\Interface\Livewire\RoleManagement::class);
        Livewire::component('tenant-manage-api-keys', \App\Modules\Tenant\Access\Interface\Livewire\ManageApiKeys::class);
        Livewire::component('tenant-notification-center', \App\Modules\Tenant\Access\Interface\Livewire\NotificationCenter::class);
        Livewire::component('tenant-data-export', \App\Modules\Tenant\Access\Interface\Livewire\DataExport::class);

        // 4. Register Event Subscriber
        Event::subscribe(\App\Modules\Tenant\Access\Application\Listeners\TenantIdentityEventSubscriber::class);

        // 5. Map API Scopes to Gates safely (Integration)
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            $scopes = request()->attributes->get('api_scopes');
            if (is_array($scopes) && in_array($ability, $scopes)) {
                return true;
            }
            return null; // Continue to other checks
        });
    }
}
