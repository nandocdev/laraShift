<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Providers;

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Tenant\Access\Application\Listeners\CreateInitialAdminUser;
use App\Modules\Tenant\Access\Application\Listeners\TenantIdentityEventSubscriber;
use App\Modules\Tenant\Access\Interface\Livewire\AcceptInvitation;
use App\Modules\Tenant\Access\Interface\Livewire\Login;
use App\Modules\Tenant\Access\Interface\Livewire\LoginChallenge;
use App\Modules\Tenant\Access\Interface\Livewire\ManageApiKeys;
use App\Modules\Tenant\Access\Interface\Livewire\RoleManagement;
use App\Modules\Tenant\Access\Interface\Livewire\TwoFactorEnrollment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'identity');

        Livewire::component('tenant-login', Login::class);
        Livewire::component('tenant-accept-invitation', AcceptInvitation::class);
        Livewire::component('tenant-login-challenge', LoginChallenge::class);
        Livewire::component('tenant-2fa-enrollment', TwoFactorEnrollment::class);
        Livewire::component('tenant-role-management', RoleManagement::class);
        Livewire::component('tenant-manage-api-keys', ManageApiKeys::class);

        // 4. Register Event Subscriber
        Event::subscribe(TenantIdentityEventSubscriber::class);

        // 5. Map API Scopes to Gates safely (Integration)
        // 6. System admin bypass: the `admin` role is granted every
        //    tenant ability so new `authorize()` calls never lock out owners.
        Gate::before(function ($user, string $ability) {
            $scopes = request()->attributes->get('api_scopes');
            if (is_array($scopes) && in_array($ability, $scopes)) {
                return true;
            }

            if (method_exists($user, 'hasRole')) {
                try {
                    if ($user->hasRole('admin')) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Spatie throws when the permission/role does not exist yet.
                }
            }

            return null; // Continue to other checks (Spatie permissions)
        });
    }
}
