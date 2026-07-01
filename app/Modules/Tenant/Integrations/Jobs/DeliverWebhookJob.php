<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Jobs;

use App\Modules\Tenant\Integrations\Models\TenantWebhook;
use App\Modules\Tenant\Integrations\Models\TenantWebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $webhookId,
        public string $event,
        public array $payload,
        public ?string $deliveryId = null,
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $webhook = TenantWebhook::find($this->webhookId);

            if (! $webhook || ! $webhook->is_active) {
                return;
            }

            $body = [
                'event' => $this->event,
                'payload' => $this->payload,
                'sent_at' => now()->toIso8601String(),
            ];

            $signature = hash_hmac('sha256', json_encode($body), $webhook->secret);

            $response = Http::timeout($webhook->timeout_seconds)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->event,
                ])
                ->post($webhook->url, $body);

            $delivery = $this->recordDelivery($webhook, $response->status(), (string) $response->body());

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'delivered',
                    'completed_at' => now(),
                ]);

                Log::info('Webhook delivered successfully', [
                    'webhook_id' => $this->webhookId,
                    'event' => $this->event,
                    'status' => $response->status(),
                ]);

                return;
            }

            $this->handleFailure($delivery, $webhook, $response->status());
        } catch (\Throwable $e) {
            Log::error('Webhook delivery exception', [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);

            $delivery = $this->recordDelivery($webhook ?? null, null, $e->getMessage());
            $this->handleFailure($delivery, $webhook ?? null, null);
        } finally {
            tenancy()->end();
        }
    }

    private function recordDelivery(?TenantWebhook $webhook, ?int $status, ?string $body): TenantWebhookDelivery
    {
        $delivery = $this->deliveryId
            ? TenantWebhookDelivery::find($this->deliveryId)
            : null;

        if ($delivery) {
            $delivery->update([
                'attempt' => $delivery->attempt + 1,
                'response_status' => $status,
                'response_body' => $body,
            ]);

            return $delivery;
        }

        return TenantWebhookDelivery::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenantId,
            'webhook_id' => $webhook?->id ?? $this->webhookId,
            'event' => $this->event,
            'payload' => $this->payload,
            'attempt' => 1,
            'status' => 'pending',
            'response_status' => $status,
            'response_body' => $body,
        ]);
    }

    private function handleFailure(TenantWebhookDelivery $delivery, ?TenantWebhook $webhook, ?int $status): void
    {
        $maxRetries = $webhook?->max_retries ?? 5;

        if ($delivery->attempt >= $maxRetries) {
            $delivery->update([
                'status' => 'dead_lettered',
                'completed_at' => now(),
            ]);

            Log::warning('Webhook moved to dead letter queue', [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
                'attempts' => $delivery->attempt,
            ]);

            return;
        }

        $backoffMinutes = min(120, 2 ** $delivery->attempt);

        $delivery->update([
            'status' => 'failed',
            'next_retry_at' => now()->addMinutes($backoffMinutes),
        ]);

        self::dispatch(
            tenantId: $this->tenantId,
            webhookId: $this->webhookId,
            event: $this->event,
            payload: $this->payload,
            deliveryId: $delivery->id,
        )->delay(now()->addMinutes($backoffMinutes));
    }
}
