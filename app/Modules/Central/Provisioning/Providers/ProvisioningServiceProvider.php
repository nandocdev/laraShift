<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Providers;

use Illuminate\Support\ServiceProvider;

class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../UI', 'provisioning');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
    }
}
