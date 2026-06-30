<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Actions;

use App\Modules\Tenant\Notifications\DTOs\UpsertNotificationTemplateData;
use App\Modules\Tenant\Notifications\Models\NotificationTemplate;

final readonly class UpsertNotificationTemplateAction
{
    public function execute(UpsertNotificationTemplateData $data): NotificationTemplate
    {
        return NotificationTemplate::updateOrCreate(
            [
                'tenant_id' => tenant('id'),
                'key' => $data->key,
                'channel' => $data->channel,
            ],
            [
                'subject' => $data->subject,
                'body' => $data->body,
                'is_active' => $data->is_active,
            ]
        );
    }
}
