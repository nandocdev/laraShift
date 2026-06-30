<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Providers;

use App\Modules\Central\Billing\Handlers\SubscriptionPaymentHandler;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\Livewire\CheckoutComponent;
use App\Modules\Central\Payments\Livewire\GatewaySettings;
use App\Modules\Central\Payments\Livewire\PayoutRequests;
use App\Modules\Central\Payments\Livewire\PayoutSettings;
use App\Modules\Central\Payments\Livewire\WebhookLog;
use App\Modules\Central\Payments\Services\Gateways\ClaveEnvironment;
use App\Modules\Central\Payments\Services\Gateways\ClaveGateway;
use App\Modules\Central\Payments\Services\Gateways\DlocalGateway;
use App\Modules\Central\Payments\Services\PaymentHandlerDispatcher;
use App\Modules\Shared\Contracts\PaymentGatewayContract;
use App\Modules\Shared\Contracts\PaymentHandlerContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClaveEnvironment::class, fn () => ClaveEnvironment::fromConfig());

        $this->app->bind(PaymentGatewayContract::class, function ($app) {
            $gateway = tenant('billing_gateway') ?? config('payments.default', 'dlocal');

            return match ($gateway) {
                'dlocal' => $app->make(DlocalGateway::class),
                default => $app->make(ClaveGateway::class),
            };
        });

        $this->app->alias(PaymentGatewayContract::class, PaymentGateway::class);

        // ── Payment Handler Bindings (Strategy Pattern) ──────────────────────
        // Tagged bindings para handlers post-pago por contexto.
        // Cada handler implementa PaymentHandlerContract y se registra aquí.
        // Para agregar un nuevo contexto (e.g. Tenant Services), simplemente
        // agregue el handler al tag 'payment.handlers'.

        $this->app->tag([
            SubscriptionPaymentHandler::class,
            // Futuro: ServiceOrderPaymentHandler::class,
        ], 'payment.handlers');

        $this->app->singleton(PaymentHandlerDispatcher::class, function ($app) {
            return new PaymentHandlerDispatcher($app->tagged('payment.handlers'));
        });
    }

    public function boot(): void
    {
        RateLimiter::for('webhooks', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        $this->loadRoutesFrom(__DIR__.'/../Routes/payments.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'payments');

        Livewire::component('payments.checkout', CheckoutComponent::class);
        Livewire::component('payments.payout-settings', PayoutSettings::class);
        Livewire::component('payments.payout-requests', PayoutRequests::class);
        Livewire::component('payments.gateway-settings', GatewaySettings::class);
        Livewire::component('payments.webhook-log', WebhookLog::class);
    }
}
