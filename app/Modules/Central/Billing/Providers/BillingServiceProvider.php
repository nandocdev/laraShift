<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Modules\Central\Billing\Infrastructure\Gateways\PaymentGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveEnvironment;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use Laravel\Cashier\Cashier;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanManager::class, function ($app) {
            return new PlanManager();
        });

        $this->app->bind(ClaveEnvironment::class, fn() => ClaveEnvironment::fromConfig());

        $this->app->bind(PaymentGateway::class, function ($app) {
            $gateway = tenant('billing_gateway') ?? config('payments.default', 'clave');

            return match ($gateway) {
                'dlocal' => new DlocalGateway(),
                default => $app->make(ClaveGateway::class),
            };
        });

        $this->app->bind(
            \App\Modules\Platform\Contracts\PaymentAmountResolverContract::class,
            \App\Modules\Central\Billing\Application\Services\PaymentAmountResolver::class
        );

        $this->app->alias(BillingManager::class, 'billing');

        // Event Listeners
        Event::listen(
            \App\Modules\Central\Billing\Domain\Events\PaymentApproved::class,
            \App\Modules\Central\Billing\Application\Listeners\FulfillSubscription::class
        );

        Event::listen(
            \App\Modules\Platform\Events\PaymentFailed::class,
            \App\Modules\Central\Billing\Application\Listeners\HandlePaymentFailure::class
        );
    }

    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);
        Cashier::useSubscriptionModel(\App\Modules\Central\Billing\Domain\Models\Subscription::class);
        Cashier::useSubscriptionItemModel(\App\Modules\Central\Billing\Domain\Models\SubscriptionItem::class);
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Central\Billing\Infrastructure\Console\ReconcileSubscriptionsCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'billing');
        $this->loadRoutesFrom(__DIR__ . '/../Interface/Routes/web.php');
        
        // Payments integration
        \Illuminate\Support\Facades\RateLimiter::for('webhooks', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
        });
        $this->loadRoutesFrom(__DIR__ . '/../Interface/Routes/payments.php');
        $this->loadViewsFrom(__DIR__ . '/../Interface/Views', 'payments');
        \Livewire\Livewire::component('payments.checkout', \App\Modules\Central\Billing\Interface\Livewire\CheckoutComponent::class);

        \Livewire\Livewire::component('billing-subscription-list', \App\Modules\Central\Billing\Interface\Livewire\SubscriptionList::class);
        \Livewire\Livewire::component('billing-tenant-invoice-list', \App\Modules\Central\Billing\Interface\Livewire\TenantInvoiceList::class);
        \Livewire\Livewire::component('billing-global-invoice-list', \App\Modules\Central\Billing\Interface\Livewire\GlobalInvoiceList::class);
        \Livewire\Livewire::component('billing-manage-billing', \App\Modules\Central\Billing\Interface\Livewire\ManageBilling::class);
        \Livewire\Livewire::component('billing-update-payment-method', \App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod::class);
        \Livewire\Livewire::component('billing-select-plan', \App\Modules\Central\Billing\Interface\Livewire\SelectPlan::class);
        \Livewire\Livewire::component('billing-hosted-checkout', \App\Modules\Central\Billing\Interface\Livewire\HostedCheckout::class);
    }
}
