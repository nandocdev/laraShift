<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Actions;

use App\Modules\Tenant\Integrations\Jobs\DeliverWebhookJob;
use App\Modules\Tenant\Integrations\Models\TenantWebhook;

final readonly class DispatchWebhookAction
{
    /**
     * Dispatch a webhook event to all active endpoints subscribed to it.
     */
    public function execute(string $event, array $payload): void
    {
        $webhooks = TenantWebhook::where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch(
                tenantId: tenant('id'),
                webhookId: $webhook->id,
                event: $event,
                payload: $payload,
            );
        }
    }
}
