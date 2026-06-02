<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Illuminate\Support\Facades\Route;

class FeaturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'features');
        
        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/features', \App\Modules\Central\Features\Livewire\FeatureList::class)->name('central.features.index');
                    Route::get('/central/features/create', \App\Modules\Central\Features\Livewire\ManageFeature::class)->name('central.features.create');
                    Route::get('/central/features/{feature}/edit', \App\Modules\Central\Features\Livewire\ManageFeature::class)->name('central.features.edit');
                    Route::get('/central/tenants/{tenant}/features/overrides', \App\Modules\Central\Features\Livewire\TenantOverrides::class)->name('central.tenants.features.overrides');
                });
        });

        Livewire::component('features-list', \App\Modules\Central\Features\Livewire\FeatureList::class);
        Livewire::component('manage-feature', \App\Modules\Central\Features\Livewire\ManageFeature::class);
        Livewire::component('tenant-overrides', \App\Modules\Central\Features\Livewire\TenantOverrides::class);
    }
}
