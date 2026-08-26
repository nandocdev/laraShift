<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Integration event fired when an inbound payment webhook has been
 * resolved to a context (central or tenant) by a gateway integration.
 *
 * Billing modules own the processing; integrations never import them.
 */
class PaymentWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $context,
        public ?string $tenantId,
        public string $rawPayload,
        public string $signature,
        public string $webhookSecret,
    ) {}
}
