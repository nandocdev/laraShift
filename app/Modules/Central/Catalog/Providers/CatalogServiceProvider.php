<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Illuminate\Support\Facades\Route;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'features');
        
        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/features', \App\Modules\Central\Catalog\Interface\Livewire\FeatureList::class)->name('central.features.index');
                    Route::get('/central/features/create', \App\Modules\Central\Catalog\Interface\Livewire\ManageFeature::class)->name('central.features.create');
                    Route::get('/central/features/{feature}/edit', \App\Modules\Central\Catalog\Interface\Livewire\ManageFeature::class)->name('central.features.edit');
                    Route::get('/central/tenants/{tenant}/features/overrides', \App\Modules\Central\Catalog\Interface\Livewire\TenantOverrides::class)->name('central.tenants.features.overrides');
                });
        });

        Livewire::component('features-list', \App\Modules\Central\Catalog\Interface\Livewire\FeatureList::class);
        Livewire::component('manage-feature', \App\Modules\Central\Catalog\Interface\Livewire\ManageFeature::class);
        Livewire::component('tenant-overrides', \App\Modules\Central\Catalog\Interface\Livewire\TenantOverrides::class);
    }
}
