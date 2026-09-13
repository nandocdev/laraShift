<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Providers;

use App\Modules\Product\Locations\Interface\Livewire\ManageLocations;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LocationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'locations');

        Livewire::component('product-manage-locations', ManageLocations::class);
    }
}
