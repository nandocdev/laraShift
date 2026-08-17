<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Events;

/**
 * Dispatched after a billable meter's aggregated usage was reported to the
 * active MeterBillingProvider. External listeners (e.g. invoicing) may react.
 */
final class MeterUsageBillingReported
{
    public function __construct(
        public readonly string $meter,
        public readonly int $quantity,
        public readonly string $period,
        public readonly string $tenantId,
    ) {}
}
