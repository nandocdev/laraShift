<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\Services;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Metering\Application\Actions\AggregateUsage;
use App\Modules\Platform\Metering\Application\Actions\RecordUsage;
use App\Modules\Platform\Metering\Application\DTO\RecordUsageData;
use App\Modules\Platform\Metering\Domain\Exceptions\MeterNotFoundException;
use App\Modules\Platform\Metering\Domain\Exceptions\TenantContextRequiredException;
use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
use App\Modules\Platform\Tenancy\Domain\Exceptions\QuotaExceededException;
use Illuminate\Support\Facades\Cache;

/**
 * Public facade of the Metering module.
 *
 * Use MeteringManager (or the Meter facade) instead of touching the models or
 * Redis counters directly. Modules that need metered usage or quota-triggered
 * recording should call record()/usage() here — never the internal actions.
 */
class MeteringManager
{
    public function __construct(
        private RecordUsage $recordUsage,
        private UsageReader $reader,
        private AggregateUsage $aggregateUsage,
    ) {}

    /**
     * Records a metered usage event for the current tenant (or an explicit one).
     *
     * @throws MeterNotFoundException
     * @throws QuotaExceededException
     */
    public function record(RecordUsageData $data, ?TenantContract $tenant = null): ?UsageEvent
    {
        return $this->recordUsage->execute($data, $tenant ?? $this->requireTenant());
    }

    /**
     * Current usage for a tenant/meter. Defaults to the current billing period.
     */
    public function usage(TenantContract $tenant, string $meter, ?string $period = null): int
    {
        return $this->reader->current(
            (string) $tenant->getId(),
            $meter,
            $period ?? now()->format('Y-m'),
        );
    }

    /**
     * Durable rollup for a tenant/meter/period, if already aggregated.
     */
    public function rollup(TenantContract $tenant, string $meter, string $period): ?UsageRollup
    {
        return UsageRollup::query()
            ->forTenant($tenant->getId())
            ->forMeter($meter)
            ->forPeriod($period)
            ->first();
    }

    /**
     * Forgets the fast-path counter for a tenant/meter/period.
     * Usage falls back to rollups / usage_events.
     */
    public function reset(TenantContract $tenant, string $meter, ?string $period = null): void
    {
        Cache::forget($this->reader->hotCounterKey(
            (string) $tenant->getId(),
            $meter,
            $period ?? now()->format('Y-m'),
        ));
    }

    /**
     * Aggregates a tenant's usage for a period (must run inside a tenant context).
     */
    public function aggregate(string $tenantId, string $period): void
    {
        $this->aggregateUsage->execute($tenantId, $period);
    }

    private function requireTenant(): TenantContract
    {
        $tenant = tenant();

        if (! $tenant instanceof TenantContract) {
            throw new TenantContextRequiredException;
        }

        return $tenant;
    }
}
