<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class InfrastructureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Central\Infrastructure\Console\Commands\HorizonUpdateCommand::class,
            ]);
        }
    }
}
