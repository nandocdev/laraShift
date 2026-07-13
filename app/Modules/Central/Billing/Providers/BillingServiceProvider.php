<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Application\Listeners\FulfillSubscription;
use App\Modules\Central\Billing\Application\Listeners\HandlePaymentFailure;
use App\Modules\Central\Billing\Application\Services\PaymentAmountResolver;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Domain\Models\SubscriptionItem;
use App\Modules\Central\Billing\Infrastructure\Console\ReconcileSubscriptionsCommand;
use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveEnvironment;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\PaymentGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\PlanManager;
use App\Modules\Central\Billing\Interface\Livewire\CheckoutComponent;
use App\Modules\Central\Billing\Interface\Livewire\GlobalInvoiceList;
use App\Modules\Central\Billing\Interface\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Interface\Livewire\ManageBilling;
use App\Modules\Central\Billing\Interface\Livewire\SelectPlan;
use App\Modules\Central\Billing\Interface\Livewire\SubscriptionList;
use App\Modules\Central\Billing\Interface\Livewire\TenantInvoiceList;
use App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\PaymentAmountResolverContract;
use App\Modules\Platform\Events\PaymentFailed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->bind(ClaveEnvironment::class, fn () => ClaveEnvironment::fromConfig());

        $this->app->bind(PaymentGateway::class, function ($app) {
            $gateway = tenant('billing_gateway') ?? config('payments.default', 'clave');

            return match ($gateway) {
                'dlocal' => $app->make(DlocalGateway::class),
                default => $app->make(ClaveGateway::class),
            };
        });

        $this->app->bind(
            PaymentAmountResolverContract::class,
            PaymentAmountResolver::class
        );

        $this->app->alias(BillingManager::class, 'billing');

        // Event Listeners
        Event::listen(
            PaymentApproved::class,
            FulfillSubscription::class
        );

        Event::listen(
            PaymentFailed::class,
            HandlePaymentFailure::class
        );
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

        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'billing');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/web.php');

        // Payments integration
        RateLimiter::for('webhooks', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/payments.php');
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'payments');
        Livewire::component('payments.checkout', CheckoutComponent::class);

        Livewire::component('billing-subscription-list', SubscriptionList::class);
        Livewire::component('billing-tenant-invoice-list', TenantInvoiceList::class);
        Livewire::component('billing-global-invoice-list', GlobalInvoiceList::class);
        Livewire::component('billing-manage-billing', ManageBilling::class);
        Livewire::component('billing-update-payment-method', UpdatePaymentMethod::class);
        Livewire::component('billing-select-plan', SelectPlan::class);
        Livewire::component('billing-hosted-checkout', HostedCheckout::class);
    }
}
