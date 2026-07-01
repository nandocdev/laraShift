<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Providers;

use App\Modules\Central\Infrastructure\Console\Commands\HorizonUpdateCommand;
use Illuminate\Support\ServiceProvider;

class InfrastructureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                HorizonUpdateCommand::class,
            ]);
        }
    }
}
