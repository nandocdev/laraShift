<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'support');

        // Note: No global routes here yet as they are either in tenant or triggered via Provisioning
    }
}
