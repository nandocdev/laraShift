<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Providers;

use App\Modules\Central\Catalog\Application\Services\CatalogPlanQuotaResolver;
use App\Modules\Central\Catalog\Application\Services\CatalogTenantFeatureResolver;
use App\Modules\Central\Catalog\Interface\Livewire\ManagePlans;
use App\Modules\Platform\Contracts\PlanQuotaResolver;
use App\Modules\Platform\Contracts\TenantFeatureResolver;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PlanQuotaResolver::class, CatalogPlanQuotaResolver::class);
        $this->app->bind(TenantFeatureResolver::class, CatalogTenantFeatureResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'catalog');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/web.php');

        Livewire::component('central-manage-plans', ManagePlans::class);
    }
}
