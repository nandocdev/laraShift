<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Integrations\Actions;

use App\Modules\Tenant\Integrations\Models\TenantWebhook;

final readonly class DeleteWebhookAction
{
    public function execute(TenantWebhook $webhook): void
    {
        $webhook->delete();
    }
}
