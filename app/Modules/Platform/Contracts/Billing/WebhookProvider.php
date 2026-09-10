<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

interface WebhookProvider extends HasBillingCapabilities
{
    public function verify(string $rawPayload, string $signature, string $secret): bool;

    public function normalize(array $payload): BillingEventData;
}
