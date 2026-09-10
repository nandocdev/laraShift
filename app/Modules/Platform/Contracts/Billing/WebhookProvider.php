<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

interface WebhookProvider
{
    public function verify(string $rawPayload, string $signature): bool;

    public function normalize(array $payload): BillingEventData;
}
