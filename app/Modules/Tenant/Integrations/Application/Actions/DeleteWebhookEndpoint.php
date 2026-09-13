<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Application\Actions;

use App\Modules\Tenant\Integrations\Domain\Models\WebhookEndpoint;

final readonly class DeleteWebhookEndpoint
{
    public function execute(WebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }
}
