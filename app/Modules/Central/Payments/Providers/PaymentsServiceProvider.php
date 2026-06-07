<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\Services\Gateways\ClaveEnvironment;
use App\Modules\Central\Payments\Services\Gateways\ClaveGateway;
use Livewire\Livewire;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClaveEnvironment::class, fn() => ClaveEnvironment::fromConfig());

        // Bind the contract to the Clave implementation.
        $this->app->bind(PaymentGateway::class, ClaveGateway::class);
    }

    public function boot(): void
    {
        // Load routes from the module
        $this->loadRoutesFrom(__DIR__ . '/../Routes/payments.php');

        // Load views from the module (Namespace: payments)
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'payments');

        // Register Livewire components
        Livewire::component('payments.checkout', \App\Modules\Central\Payments\Livewire\CheckoutComponent::class);
    }
}
