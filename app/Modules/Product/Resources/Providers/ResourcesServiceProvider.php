<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Providers;

use App\Modules\Product\Resources\Interface\Livewire\ManageResources;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ResourcesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'resources');

        Livewire::component('product-manage-resources', ManageResources::class);
    }
}
