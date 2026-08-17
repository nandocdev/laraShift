<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Infrastructure\Providers;

use App\Modules\Platform\Metering\Application\Actions\AggregateUsage;
use App\Modules\Platform\Metering\Application\Actions\RecordUsage;
use App\Modules\Platform\Metering\Application\Actions\ReportUsageToBilling;
use App\Modules\Platform\Metering\Application\Services\MeteringManager;
use App\Modules\Platform\Metering\Application\Services\UsageReader;
use App\Modules\Platform\Metering\Domain\MeterRegistry;
use App\Modules\Platform\Metering\Infrastructure\Console\AggregateUsageCommand;
use App\Modules\Platform\Metering\Support\Facades\Meter;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class MeteringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MeterRegistry::class, function ($app) {
            return new MeterRegistry((array) config('metering.meters', []));
        });

        $this->app->singleton(MeteringManager::class);
        $this->app->singleton(UsageReader::class);
        $this->app->singleton(RecordUsage::class);
        $this->app->singleton(AggregateUsage::class);
        $this->app->singleton(ReportUsageToBilling::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AggregateUsageCommand::class,
            ]);
        }

        AliasLoader::getInstance()->alias('Meter', Meter::class);
    }
}
