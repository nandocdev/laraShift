<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Modules\Central\Payments\Actions\HandleWebhookAction;
use App\Modules\Central\Payments\Exceptions\WebhookVerificationException;
use Throwable;

final class ProcessPaymentWebhookJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $rawPayload,
        public readonly string $signature,
        public readonly string $webhookSecret,
    ) {
        $this->onQueue('webhooks-priority');
    }

    public function handle(): void {
        // Tenant context must be initialized before business logic.
        // This follows the mandatory queue isolation pattern in Architecture.md.
        tenancy()->initialize($this->tenantId);

        try {
            $action = app(HandleWebhookAction::class);

            $action->execute(
                rawPayload: $this->rawPayload,
                signature: $this->signature,
                webhookSecret: $this->webhookSecret,
                tenantId: $this->tenantId,
            );
        } finally {
            tenancy()->end();
        }
    }

    public function failed(Throwable $e): void {
        // Signature failures are not retryable. Exhaust immediately.
        if ($e instanceof WebhookVerificationException) {
            Log::critical('ClaveGateway: webhook signature failure — not retrying', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);

            return;
        }

        Log::error('ProcessPaymentWebhookJob failed', [
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
        ]);
    }
}
