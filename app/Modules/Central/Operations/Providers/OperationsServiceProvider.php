<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Providers;

use App\Modules\Central\Operations\Infrastructure\Console\HorizonUpdateCommand;
use Illuminate\Support\ServiceProvider;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                HorizonUpdateCommand::class,
            ]);
        }
    }
}
