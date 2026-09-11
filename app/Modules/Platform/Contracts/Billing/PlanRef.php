<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class PlanRef extends Data
{
    public function __construct(
        public string $slug,
        public int $amountCents,
        public string $currency,
        /** @var array<string, string> */
        public array $gatewayIds = [],
    ) {}
}
