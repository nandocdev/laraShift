<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Event;
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

        // Event Listeners
        Event::listen(
            \App\Modules\Central\Payments\Events\PaymentApproved::class,
            \App\Modules\Central\Billing\Listeners\FulfillSubscription::class
        );
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        Cashier::useSubscriptionModel(\App\Modules\Central\Billing\Models\Subscription::class);
        Cashier::useSubscriptionItemModel(\App\Modules\Central\Billing\Models\SubscriptionItem::class);
        
        $this->loadViewsFrom(__DIR__ . '/../UI', 'billing');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        \Livewire\Livewire::component('billing-subscription-list', \App\Modules\Central\Billing\Livewire\SubscriptionList::class);
        \Livewire\Livewire::component('billing-tenant-invoice-list', \App\Modules\Central\Billing\Livewire\TenantInvoiceList::class);
        \Livewire\Livewire::component('billing-global-invoice-list', \App\Modules\Central\Billing\Livewire\GlobalInvoiceList::class);
        \Livewire\Livewire::component('billing-manage-billing', \App\Modules\Central\Billing\Livewire\ManageBilling::class);
        \Livewire\Livewire::component('billing-update-payment-method', \App\Modules\Central\Billing\Livewire\UpdatePaymentMethod::class);
        \Livewire\Livewire::component('billing-ledger-audit', \App\Modules\Central\Billing\Livewire\LedgerAudit::class);
        \Livewire\Livewire::component('billing-select-plan', \App\Modules\Central\Billing\Livewire\SelectPlan::class);
        \Livewire\Livewire::component('billing-hosted-checkout', \App\Modules\Central\Billing\Livewire\HostedCheckout::class);
    }
}
