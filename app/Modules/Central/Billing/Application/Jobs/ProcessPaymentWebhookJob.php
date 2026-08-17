<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Jobs;

use App\Modules\Central\Billing\Application\Actions\HandleWebhook;
use App\Modules\Central\Billing\Domain\Exceptions\WebhookVerificationException;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessPaymentWebhookJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

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

    public function handle(): void
    {
        try {
            app(HandleWebhook::class)->execute(
                $this->rawPayload,
                $this->signature,
                $this->webhookSecret,
                $this->tenantId,
            );
        } catch (WebhookVerificationException $e) {
            Log::warning('Webhook verification failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('ProcessPaymentWebhookJob failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
