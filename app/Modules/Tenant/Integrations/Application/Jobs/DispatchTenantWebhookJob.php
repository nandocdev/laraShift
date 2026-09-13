<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Security\Hmac\HmacSigner;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Tenant\Integrations\Application\Actions\DispatchTenantWebhook;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookDelivery;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one event to one endpoint, HMAC-SHA256 signed (UC-T-06).
 * Up to 5 attempts with exponential backoff; every attempt updates
 * the same delivery row for partner debugging.
 */
final class DispatchTenantWebhookJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $deliveryId,
    ) {}

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $endpoint = WebhookEndpoint::find($delivery->endpoint_id);

        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'cancelled', 'error' => 'Endpoint removed or disabled.']);

            return;
        }

        $body = json_encode([
            'id' => DispatchTenantWebhook::eventId($endpoint->id, $delivery->event_type),
            'type' => $delivery->event_type,
            'created' => now()->toIso8601String(),
            'data' => $delivery->payload ?? [],
        ], JSON_THROW_ON_ERROR);

        $attempts = $this->attempts();

        try {
            $response = Http::timeout(10)->withBody($body, 'application/json')
                ->withHeaders(['X-Signature-SHA256' => HmacSigner::hash($body, $endpoint->secret)])
                ->post($endpoint->url);

            $delivery->update([
                'attempts' => $attempts,
                'response_status' => $response->status(),
            ]);

            if ($response->successful()) {
                $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);

                return;
            }

            $delivery->update(['status' => 'failed', 'error' => "HTTP {$response->status()}"]);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed', 'attempts' => $attempts, 'error' => substr($e->getMessage(), 0, 500)]);
            throw $e;
        }

        throw new \RuntimeException("Webhook delivery failed for endpoint {$endpoint->id}.");
    }
}
