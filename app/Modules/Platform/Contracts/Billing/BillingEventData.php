<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class BillingEventData extends Data
{
    public function __construct(
        public readonly BillingEventType $type,
        public readonly string $providerReference,
        public readonly string $displayId,
        public readonly int $amountCents,
    ) {}
}
