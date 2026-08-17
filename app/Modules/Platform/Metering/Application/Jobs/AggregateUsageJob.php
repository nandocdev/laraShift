<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Metering\Application\Actions\AggregateUsage;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Aggregates a single tenant's metered usage for a billing period and reports
 * billable meters to the active MeterBillingProvider.
 */
class AggregateUsageJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $period,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RehydrateTenantContext];
    }

    public function handle(AggregateUsage $action): void
    {
        $action->execute($this->tenantId, $this->period);
    }
}
