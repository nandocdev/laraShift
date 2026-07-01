<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Providers;

use App\Modules\Central\Features\Livewire\FeatureChangeHistory;
use App\Modules\Central\Features\Livewire\FeatureList;
use App\Modules\Central\Features\Livewire\ManageFeature;
use App\Modules\Central\Features\Livewire\TenantOverrides;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FeaturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../UI', 'features');

        $this->app->booted(function () {
            Route::middleware(['web', 'auth:central'])
                ->group(function () {
                    Route::get('/central/features', FeatureList::class)->name('central.features.index');
                    Route::get('/central/features/create', ManageFeature::class)->name('central.features.create');
                    Route::get('/central/features/{feature}/edit', ManageFeature::class)->name('central.features.edit');
                    Route::get('/central/features/history', FeatureChangeHistory::class)->name('central.features.history');
                    Route::get('/central/tenants/{tenant}/features/overrides', TenantOverrides::class)->name('central.tenants.features.overrides');
                });
        });

        Livewire::component('features-list', FeatureList::class);
        Livewire::component('manage-feature', ManageFeature::class);
        Livewire::component('tenant-overrides', TenantOverrides::class);
        Livewire::component('feature-change-history', FeatureChangeHistory::class);
    }
}
