<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Events;

use Carbon\CarbonImmutable;

final class MeterUsageRecorded
{
    public function __construct(
        public readonly string $meter,
        public readonly int $quantity,
        public readonly string $period,
        public readonly string $tenantId,
        public readonly CarbonImmutable $occurredAt,
        public readonly array $metadata = [],
    ) {}
}
