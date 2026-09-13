<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Providers;

use App\Modules\Central\Operations\Infrastructure\Console\AlertCriticalIncidentsCommand;
use App\Modules\Central\Operations\Infrastructure\Console\HorizonUpdateCommand;
use App\Modules\Central\Operations\Interface\Livewire\HealthMonitor;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Interface/Views', 'operations');
        $this->loadRoutesFrom(__DIR__.'/../Interface/Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                HorizonUpdateCommand::class,
                AlertCriticalIncidentsCommand::class,
            ]);
        }

        Livewire::component('operations-health-monitor', HealthMonitor::class);
    }
}
