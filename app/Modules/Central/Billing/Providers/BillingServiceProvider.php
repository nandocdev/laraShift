<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Providers;

use App\Modules\Central\Billing\Application\Listeners\FulfillSubscription;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveEnvironment;
use App\Modules\Central\Billing\Infrastructure\Gateways\DefaultBillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\Dlocal\DlocalHttpClient;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClaveEnvironment::class, fn () => ClaveEnvironment::fromConfig());
        $this->app->singleton(DlocalHttpClient::class, fn () => DlocalHttpClient::fromConfig());
        $this->app->bind(BillingManager::class, DefaultBillingManager::class);

        Event::listen(PaymentApproved::class, FulfillSubscription::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/payments.php');
    }
}
