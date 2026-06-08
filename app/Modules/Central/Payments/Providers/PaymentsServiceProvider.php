<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\Services\Gateways\ClaveEnvironment;
use App\Modules\Central\Payments\Services\Gateways\ClaveGateway;
use App\Modules\Central\Payments\Services\Gateways\DlocalGateway;
use Livewire\Livewire;

final class PaymentsServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->bind(ClaveEnvironment::class, fn() => ClaveEnvironment::fromConfig());

        $this->app->bind(PaymentGateway::class, function ($app) {
            $gateway = tenant('billing_gateway') ?? config('payments.default', 'clave');

            return match ($gateway) {
                'dlocal' => new DlocalGateway(),
                default => $app->make(ClaveGateway::class),
            };
        });
    }

    public function boot(): void {
        \Illuminate\Support\Facades\RateLimiter::for('webhooks', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
        });

        $this->loadRoutesFrom(__DIR__ . '/../Routes/payments.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'payments');

        Livewire::component('payments.checkout', \App\Modules\Central\Payments\Livewire\CheckoutComponent::class);
    }
}
