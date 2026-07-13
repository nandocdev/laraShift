<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Providers;

use App\Modules\Platform\Integrations\Dlocal\Client\DlocalHttpClient;
use App\Modules\Platform\Integrations\Dlocal\Contracts\PaymentGatewayContract;
use App\Modules\Platform\Integrations\Dlocal\DlocalPaymentGateway;
use Illuminate\Support\ServiceProvider;

class DlocalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DlocalHttpClient::class, function () {
            return new DlocalHttpClient(
                baseUrl: config('dlocal.environment') === 'production'
                    ? 'https://api.dlocal.com'
                    : 'https://sandbox.dlocal.com',
                login: (string) config('dlocal.login'),
                transKey: (string) config('dlocal.trans_key'),
                secretKey: (string) config('dlocal.secret_key'),
            );
        });

        $this->app->bind(PaymentGatewayContract::class, function ($app) {
            return new DlocalPaymentGateway(
                $app->make(DlocalHttpClient::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/webhooks.php');
    }
}
