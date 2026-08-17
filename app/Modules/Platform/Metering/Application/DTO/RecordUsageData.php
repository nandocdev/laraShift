<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\DTO;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

final class RecordUsageData extends Data
{
    public function __construct(
        /** Registered meter key (see config/metering.php). */
        public readonly string $meter,

        #[Min(1)]
        public readonly int $quantity = 1,

        /** When the usage occurred. Defaults to now(). */
        public readonly ?CarbonInterface $occurredAt = null,

        /** Arbitrary key-value context. For unique aggregation use metadata['key']. */
        public readonly array $metadata = [],

        /**
         * Idempotency key. Events sharing the same tenant + dedupeKey are
         * recorded only once (enables safe retries of producers).
         */
        public readonly ?string $dedupeKey = null,

        /**
         * When true, the plan quota for this meter is enforced before recording:
         * exceeding it throws QuotaExceededException and nothing is persisted.
         */
        public readonly bool $enforceQuota = false,

        /** Optional linkage to the subscription item used for billing. */
        public readonly ?string $subscriptionItemId = null,
    ) {}
}
