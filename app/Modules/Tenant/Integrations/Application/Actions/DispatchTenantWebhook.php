<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\Actions;

use App\Modules\Tenant\Integrations\Application\Jobs\DispatchTenantWebhookJob;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookDelivery;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Fans out a tenant event to every active endpoint subscribed to it.
 * Delivery itself is async per endpoint (own job, own retries).
 */
final readonly class DispatchTenantWebhook
{
    /**
     * Events with at least one producer. Endpoints may only subscribe
     * to these; adding a name here without wiring a producer is a bug.
     */
    public const EVENTS = [
        'api_key.created',
    ];

    /**
     * @param  array<string, mixed>  $payload  JSON-serializable event data (never secrets).
     */
    public function execute(string $tenantId, string $event, array $payload = []): int
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException("Unknown webhook event: {$event}");
        }

        $dispatched = 0;

        WebhookEndpoint::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->each(function (WebhookEndpoint $endpoint) use ($tenantId, $event, $payload, &$dispatched): void {
                if (! $endpoint->listensTo($event)) {
                    return;
                }

                $delivery = WebhookDelivery::create([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenantId,
                    'endpoint_id' => $endpoint->id,
                    'event_type' => $event,
                    'payload' => $payload,
                    'status' => 'pending',
                ]);

                DispatchTenantWebhookJob::dispatch(
                    tenantId: $tenantId,
                    deliveryId: (string) $delivery->id,
                );

                $dispatched++;
            });

        return $dispatched;
    }

    public static function eventId(string $endpointId, string $event): string
    {
        return 'evt_'.Str::random(24);
    }
}
