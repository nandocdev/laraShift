<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Actions;

use App\Modules\Tenant\Integrations\DTOs\CreateWebhookData;
use App\Modules\Tenant\Integrations\Models\TenantWebhook;
use Illuminate\Support\Str;

final readonly class CreateWebhookAction
{
    public function execute(CreateWebhookData $data): TenantWebhook
    {
        return TenantWebhook::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'url' => $data->url,
            'secret' => $data->secret,
            'events' => $data->events,
            'is_active' => $data->is_active,
            'max_retries' => $data->max_retries,
            'timeout_seconds' => $data->timeout_seconds,
        ]);
    }
}
