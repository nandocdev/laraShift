<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Providers;

use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Listeners\CreateInitialAdminUser;
use App\Modules\Tenant\Identity\Listeners\TenantIdentityEventSubscriber;
use App\Modules\Tenant\Identity\Livewire\AcceptInvitation;
use App\Modules\Tenant\Identity\Livewire\DataExport;
use App\Modules\Tenant\Identity\Livewire\Login;
use App\Modules\Tenant\Identity\Livewire\LoginChallenge;
use App\Modules\Tenant\Identity\Livewire\ManageApiKeys;
use App\Modules\Tenant\Identity\Livewire\NotificationCenter;
use App\Modules\Tenant\Identity\Livewire\RoleManagement;
use App\Modules\Tenant\Identity\Livewire\TeamManagement;
use App\Modules\Tenant\Identity\Livewire\TwoFactorEnrollment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->loadViewsFrom(__DIR__.'/../UI', 'identity');

        Livewire::component('tenant-login', Login::class);
        Livewire::component('tenant-accept-invitation', AcceptInvitation::class);
        Livewire::component('tenant-login-challenge', LoginChallenge::class);
        Livewire::component('tenant-2fa-enrollment', TwoFactorEnrollment::class);
        Livewire::component('tenant-team-management', TeamManagement::class);
        Livewire::component('tenant-role-management', RoleManagement::class);
        Livewire::component('tenant-manage-api-keys', ManageApiKeys::class);
        Livewire::component('tenant-notification-center', NotificationCenter::class);
        Livewire::component('tenant-data-export', DataExport::class);

        // 4. Register Event Subscriber
        Event::subscribe(TenantIdentityEventSubscriber::class);

        // 5. Map API Scopes to Gates safely (Integration)
        Gate::before(function ($user, string $ability) {
            $scopes = request()->attributes->get('api_scopes');
            if (is_array($scopes) && in_array($ability, $scopes)) {
                return true;
            }

            return null; // Continue to other checks
        });
    }
}
