<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class ProviderSubscriptionRef extends Data
{
    public function __construct(
        public readonly string $providerSubscriptionId,
        public readonly string $status,
        public readonly string $provider,
    ) {}
}
