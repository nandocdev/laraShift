<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingManager::class, function ($app) {
            return new BillingManager($app);
        });

        $this->app->alias(BillingManager::class, 'billing');
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        
        $this->loadViewsFrom(__DIR__ . '/../UI', 'billing');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        \Livewire\Livewire::component('billing-subscription-list', \App\Modules\Central\Billing\Livewire\SubscriptionList::class);
        \Livewire\Livewire::component('billing-tenant-invoice-list', \App\Modules\Central\Billing\Livewire\TenantInvoiceList::class);
    }
}
