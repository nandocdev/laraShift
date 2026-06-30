<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Console\Commands\ReconcileSubscriptionsCommand;
use App\Modules\Central\Billing\Livewire\GlobalInvoiceList;
use App\Modules\Central\Billing\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Livewire\ManageBilling;
use App\Modules\Central\Billing\Livewire\ReportsView;
use App\Modules\Central\Billing\Livewire\SelectPlan;
use App\Modules\Central\Billing\Livewire\SubscriptionDetail;
use App\Modules\Central\Billing\Livewire\SubscriptionList;
use App\Modules\Central\Billing\Livewire\TenantInvoiceList;
use App\Modules\Central\Billing\Livewire\UpdatePaymentMethod;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Billing\Models\SubscriptionItem;
use App\Modules\Central\Billing\Services\PaymentAmountResolver;
use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\PaymentAmountResolverContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanManager::class, function ($app) {
            return new PlanManager;
        });

        $this->app->bind(
            PaymentAmountResolverContract::class,
            PaymentAmountResolver::class
        );

        $this->app->alias(BillingManager::class, 'billing');

        // Legacy event listeners removed (migrated to SubscriptionPaymentHandler).
        // Post-payment logic is now handled via PaymentHandlerDispatcher strategy pattern.
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileSubscriptionsCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__.'/../UI', 'billing');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Livewire::component('billing-subscription-list', SubscriptionList::class);
        Livewire::component('billing-tenant-invoice-list', TenantInvoiceList::class);
        Livewire::component('billing-global-invoice-list', GlobalInvoiceList::class);
        Livewire::component('billing-manage-billing', ManageBilling::class);
        Livewire::component('billing-update-payment-method', UpdatePaymentMethod::class);
        Livewire::component('billing-select-plan', SelectPlan::class);
        Livewire::component('billing-hosted-checkout', HostedCheckout::class);
        Livewire::component('billing-subscription-detail', SubscriptionDetail::class);
        Livewire::component('billing-reports-view', ReportsView::class);
    }
}
