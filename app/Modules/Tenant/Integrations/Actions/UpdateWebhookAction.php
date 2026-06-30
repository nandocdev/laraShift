<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Actions;

use App\Modules\Tenant\Integrations\DTOs\UpdateWebhookData;
use App\Modules\Tenant\Integrations\Models\TenantWebhook;

final readonly class UpdateWebhookAction
{
    public function execute(TenantWebhook $webhook, UpdateWebhookData $data): TenantWebhook
    {
        $webhook->update(array_filter([
            'url' => $data->url,
            'secret' => $data->secret,
            'events' => $data->events,
            'is_active' => $data->is_active,
            'max_retries' => $data->max_retries,
            'timeout_seconds' => $data->timeout_seconds,
        ], fn ($v) => $v !== null));

        return $webhook->fresh();
    }
}
