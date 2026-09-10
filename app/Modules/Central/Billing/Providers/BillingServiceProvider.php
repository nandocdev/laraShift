<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Application\Listeners\FulfillSubscription;
use App\Modules\Central\Billing\Application\Listeners\HandlePaymentFailure;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Events\PaymentDeclined;
use App\Modules\Central\Billing\Infrastructure\Console\ProcessRecurringChargesCommand;
use App\Modules\Central\Billing\Infrastructure\Console\ReconcileSubscriptionsCommand;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveEnvironment;
use App\Modules\Central\Billing\Infrastructure\Gateways\DefaultBillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\Dlocal\DlocalHttpClient;
use App\Modules\Central\Billing\Interface\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Interface\Livewire\ManageBilling;
use App\Modules\Central\Billing\Interface\Livewire\SelectPlan;
use App\Modules\Central\Billing\Interface\Livewire\SubscriptionList;
use App\Modules\Central\Billing\Interface\Livewire\TenantInvoiceList;
use App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClaveEnvironment::class, fn () => ClaveEnvironment::fromConfig());
        $this->app->singleton(DlocalHttpClient::class, fn () => DlocalHttpClient::fromConfig());
        $this->app->bind(BillingManager::class, DefaultBillingManager::class);

        Event::listen(PaymentApproved::class, FulfillSubscription::class);
        Event::listen(PaymentDeclined::class, HandlePaymentFailure::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'billing');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/payments.php');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/web.php');

        Livewire::component('billing-select-plan', SelectPlan::class);
        Livewire::component('billing-manage-billing', ManageBilling::class);
        Livewire::component('billing-hosted-checkout', HostedCheckout::class);
        Livewire::component('billing-update-payment-method', UpdatePaymentMethod::class);
        Livewire::component('billing-subscription-list', SubscriptionList::class);
        Livewire::component('billing-tenant-invoice-list', TenantInvoiceList::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessRecurringChargesCommand::class,
                ReconcileSubscriptionsCommand::class,
            ]);
        }
    }
}
