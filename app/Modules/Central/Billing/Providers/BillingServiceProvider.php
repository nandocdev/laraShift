<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        
        $this->loadViewsFrom(__DIR__ . '/../UI', 'billing');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
    }
}
