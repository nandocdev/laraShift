<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Providers;

use App\Modules\Product\Services\Interface\Livewire\ManageServices;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'services');

        Livewire::component('product-manage-services', ManageServices::class);
    }
}
