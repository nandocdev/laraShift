<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Infrastructure\Gateways\PaymentVerifier;

final readonly class HandleWebhook {
    public function __construct(
        private PaymentVerifier $verifier,
    ) {
    }

    public function execute(
        string $rawPayload,
        string $signature,
        string $webhookSecret,
        string $tenantId,
    ): void {
        $this->verifier->handleWebhook($rawPayload, $signature, $webhookSecret, $tenantId);
    }
}
