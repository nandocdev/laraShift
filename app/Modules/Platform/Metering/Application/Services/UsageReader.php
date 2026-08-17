<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\Services;

use App\Modules\Platform\Metering\Domain\Enums\AggregationType;
use App\Modules\Platform\Metering\Domain\MeterRegistry;
use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
use App\Modules\Platform\Metering\Domain\UsagePeriod;
use Illuminate\Support\Facades\Cache;

/**
 * Fast-path reader of current usage for a tenant/meter/period.
 *
 * Resolution order: hot Redis counter (sum meters) -> durable rollup -> DB scan
 * of usage_events. The DB scan is the safety net that guarantees correctness
 * even after a cache flush.
 */
final readonly class UsageReader
{
    public function __construct(
        private MeterRegistry $registry,
    ) {}

    public function current(string|int $tenantId, string $meter, string $period): int
    {
        $meterConfig = $this->registry->get($meter);

        if ($meterConfig->aggregation === AggregationType::Sum) {
            $hot = Cache::get($this->hotCounterKey($tenantId, $meter, $period));

            if ($hot !== null) {
                return (int) $hot;
            }
        }

        $rollup = UsageRollup::query()
            ->forTenant($tenantId)
            ->forMeter($meter)
            ->forPeriod($period)
            ->first();

        if ($rollup !== null) {
            return (int) $rollup->value;
        }

        $usagePeriod = UsagePeriod::from($period);

        $query = UsageEvent::query()
            ->forTenant($tenantId)
            ->forMeter($meter)
            ->whereBetween('occurred_at', [$usagePeriod->start, $usagePeriod->end]);

        return match ($meterConfig->aggregation) {
            AggregationType::Sum => (int) $query->sum('quantity'),
            AggregationType::Max => (int) ($query->max('quantity') ?? 0),
        };
    }

    public function hotCounterKey(string|int $tenantId, string $meter, string $period): string
    {
        return "metering:usage:{$tenantId}:{$meter}:{$period}";
    }
}
