<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Central\Payments\Services\PaymentVerifier;

final readonly class HandleWebhookAction
{
    public function __construct(
        private PaymentVerifier $verifier,
    ) {}

    public function execute(
        string $rawPayload,
        string $signature,
        string $webhookSecret,
        string $tenantId,
    ): void {
        $this->verifier->handleWebhook($rawPayload, $signature, $webhookSecret, $tenantId);
    }
}
