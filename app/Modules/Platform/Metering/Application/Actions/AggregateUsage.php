<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\Actions;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Metering\Domain\Enums\AggregationType;
use App\Modules\Platform\Metering\Domain\Exceptions\TenantContextRequiredException;
use App\Modules\Platform\Metering\Domain\Meter;
use App\Modules\Platform\Metering\Domain\MeterRegistry;
use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
use App\Modules\Platform\Metering\Domain\UsagePeriod;

/**
 * Aggregates a tenant's metered usage for a closed billing period into durable
 * usage_rollups and reports billable meters to the active billing provider.
 *
 * Must run inside a tenant context (AggregateUsageJob rehydrates it via
 * RehydrateTenantContext). Idempotent: re-running updates the same rollups.
 */
final readonly class AggregateUsage
{
    public function __construct(
        private MeterRegistry $registry,
        private ReportUsageToBilling $reportUsage,
    ) {}

    public function execute(string $tenantId, string $period): void
    {
        $tenant = $this->requireTenantContext();

        $usagePeriod = UsagePeriod::from($period);

        foreach ($this->registry->all() as $meter) {
            $value = $this->aggregateMeter($tenantId, $meter, $usagePeriod);

            $rollup = UsageRollup::query()
                ->forTenant($tenantId)
                ->forMeter($meter->key)
                ->forPeriod($period)
                ->first();

            if ($value === 0 && $rollup === null) {
                continue;
            }

            $rollup ??= new UsageRollup;
            $rollup->forceFill([
                'tenant_id' => $tenantId,
                'meter' => $meter->key,
                'period' => $period,
                'value' => $value,
                'aggregation' => $meter->aggregation->value,
            ])->save();

            $this->reportUsage->execute($tenant, $meter, $value, $period, $rollup);
        }
    }

    private function aggregateMeter(string $tenantId, Meter $meter, UsagePeriod $usagePeriod): int
    {
        $query = UsageEvent::query()
            ->forTenant($tenantId)
            ->forMeter($meter->key)
            ->whereBetween('occurred_at', [$usagePeriod->start, $usagePeriod->end]);

        return match ($meter->aggregation) {
            AggregationType::Sum => (int) $query->sum('quantity'),
            AggregationType::Max => (int) ($query->max('quantity') ?? 0),
        };
    }

    private function requireTenantContext(): TenantContract
    {
        $tenant = tenant();

        if (! $tenant instanceof TenantContract) {
            throw new TenantContextRequiredException;
        }

        return $tenant;
    }
}
