<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Actions;

use App\Modules\Tenant\Notifications\Models\NotificationTemplate;

final readonly class DeleteNotificationTemplateAction
{
    public function execute(string $templateId): void
    {
        NotificationTemplate::where('id', $templateId)
            ->where('tenant_id', tenant('id'))
            ->delete();
    }
}
