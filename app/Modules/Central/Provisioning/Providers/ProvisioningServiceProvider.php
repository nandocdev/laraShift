<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Providers;

use App\Modules\Central\Provisioning\Livewire\CreateTenant;
use App\Modules\Central\Provisioning\Livewire\ManageTenant;
use App\Modules\Central\Provisioning\Livewire\TenantList;
use App\Modules\Central\Provisioning\Services\TenantDomainResolver;
use App\Modules\Shared\Contracts\TenantDomainResolverContract;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TenantDomainResolverContract::class,
            TenantDomainResolver::class
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'provisioning');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Livewire::component('provisioning-tenant-list', TenantList::class);
        Livewire::component('provisioning-create-tenant', CreateTenant::class);
        Livewire::component('provisioning-manage-tenant', ManageTenant::class);
    }
}
