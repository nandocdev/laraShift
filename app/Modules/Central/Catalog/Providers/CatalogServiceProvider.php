<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Providers;

use App\Modules\Central\Catalog\Application\Actions\ResolveTenantFeatures;
use App\Modules\Central\Catalog\Interface\Livewire\FeatureList;
use App\Modules\Central\Catalog\Interface\Livewire\ManageFeature;
use App\Modules\Central\Catalog\Interface\Livewire\TenantOverrides;
use App\Modules\Platform\Contracts\FeatureResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FeatureResolver::class, ResolveTenantFeatures::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'features');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/features', FeatureList::class)->name('central.features.index');
                    Route::get('/central/features/create', ManageFeature::class)->name('central.features.create');
                    Route::get('/central/features/{feature}/edit', ManageFeature::class)->name('central.features.edit');
                    Route::get('/central/tenants/{tenant}/features/overrides', TenantOverrides::class)->name('central.tenants.features.overrides');
                });
        });

        Livewire::component('features-list', FeatureList::class);
        Livewire::component('manage-feature', ManageFeature::class);
        Livewire::component('tenant-overrides', TenantOverrides::class);
    }
}
