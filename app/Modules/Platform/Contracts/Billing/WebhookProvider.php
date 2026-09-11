<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

interface WebhookProvider
{
    public function verify(string $rawPayload, string $signature): bool;

    /**
     * Same as verify(), but throws a domain exception on mismatch instead
     * of returning false.
     */
    public function verifyOrFail(string $rawPayload, string $signature): void;

    public function normalize(array $payload): BillingEventData;
}
