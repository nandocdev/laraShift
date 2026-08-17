<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Infrastructure\Console;

use App\Modules\Platform\Metering\Application\Jobs\AggregateUsageJob;
use App\Modules\Platform\Metering\Domain\UsagePeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateUsageCommand extends Command
{
    protected $signature = 'metering:aggregate {--period= : Billing period to aggregate (YYYY-MM). Defaults to the current period.} {--tenant= : Only aggregate a specific tenant ID.}';

    protected $description = 'Aggregate metered usage into rollups and report billable meters to the billing provider';

    public function handle(): int
    {
        try {
            $period = $this->option('period') ?? now()->format('Y-m');
            UsagePeriod::from($period);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');

        if ($tenantId !== null) {
            $this->dispatchForTenant((string) $tenantId, $period);

            return self::SUCCESS;
        }

        $query = DB::table('tenants')->select('id')->whereNull('deleted_at')->whereNull('archived_at');

        $count = 0;
        $query->chunkById(100, function ($tenants) use ($period, &$count) {
            foreach ($tenants as $tenant) {
                $this->dispatchForTenant((string) $tenant->id, $period);
                $count++;
            }
        });

        $this->info("Dispatched usage aggregation for {$count} tenants [{$period}].");

        return self::SUCCESS;
    }

    private function dispatchForTenant(string $tenantId, string $period): void
    {
        AggregateUsageJob::dispatch($tenantId, $period);
    }
}
