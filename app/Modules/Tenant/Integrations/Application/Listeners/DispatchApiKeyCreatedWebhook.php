<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\Listeners;

use App\Modules\Platform\Events\TenantApiKeyCreated;
use App\Modules\Tenant\Integrations\Application\Actions\DispatchTenantWebhook;

/**
 * First producer wired to outgoing webhooks: partners subscribed to
 * api_key.created are notified whenever a key is issued.
 */
final readonly class DispatchApiKeyCreatedWebhook
{
    public function __construct(
        private DispatchTenantWebhook $dispatch,
    ) {}

    public function handle(TenantApiKeyCreated $event): void
    {
        $this->dispatch->execute($event->tenantId, 'api_key.created', [
            'key_id' => $event->keyId,
            'name' => $event->keyName,
            'scopes' => $event->scopes,
        ]);
    }
}
