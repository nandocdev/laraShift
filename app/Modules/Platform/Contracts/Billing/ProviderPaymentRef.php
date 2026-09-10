<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class ProviderPaymentRef extends Data
{
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly string $displayId,
        public readonly int $amountCents,
        public readonly string $provider,
    ) {}
}
