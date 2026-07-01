<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Actions;

use App\Modules\Tenant\Integrations\Jobs\DeliverWebhookJob;
use App\Modules\Tenant\Integrations\Models\TenantWebhookDelivery;

final readonly class RetryWebhookDeliveryAction
{
    /**
     * Retry a failed or dead-lettered delivery.
     */
    public function execute(TenantWebhookDelivery $delivery): void
    {
        $webhook = $delivery->webhook;

        if (! $webhook || ! $webhook->is_active) {
            throw new \RuntimeException(__('Webhook endpoint is inactive or not found.'));
        }

        $delivery->update([
            'status' => 'pending',
            'next_retry_at' => now()->addMinutes(1),
        ]);

        DeliverWebhookJob::dispatch(
            tenantId: $delivery->tenant_id,
            webhookId: $webhook->id,
            event: $delivery->event,
            payload: $delivery->payload,
            deliveryId: $delivery->id,
        );
    }
}
