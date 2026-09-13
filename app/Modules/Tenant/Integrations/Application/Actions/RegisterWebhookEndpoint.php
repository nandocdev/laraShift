<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\Actions;

use App\Modules\Tenant\Integrations\Application\DTO\WebhookEndpointData;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Registers a partner endpoint (UC-T-06). Returns the endpoint plus
 * the plain secret, shown ONCE — only the encrypted value persists.
 */
final readonly class RegisterWebhookEndpoint
{
    /**
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function execute(WebhookEndpointData $data): array
    {
        $endpoint = WebhookEndpoint::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'url' => $data->url,
            'events' => $data->events,
            'secret' => $secret = 'whsec_'.Str::random(32),
            'is_active' => true,
        ]);

        return ['endpoint' => $endpoint, 'secret' => $secret];
    }
}
