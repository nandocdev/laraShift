<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Providers;

use Illuminate\Support\ServiceProvider;

class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\Shared\Contracts\TenantDomainResolverContract::class,
            \App\Modules\Central\Provisioning\Services\TenantDomainResolver::class
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'provisioning');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        \Livewire\Livewire::component('provisioning-tenant-list', \App\Modules\Central\Provisioning\Livewire\TenantList::class);
        \Livewire\Livewire::component('provisioning-create-tenant', \App\Modules\Central\Provisioning\Livewire\CreateTenant::class);
        \Livewire\Livewire::component('provisioning-manage-tenant', \App\Modules\Central\Provisioning\Livewire\ManageTenant::class);
    }
}
