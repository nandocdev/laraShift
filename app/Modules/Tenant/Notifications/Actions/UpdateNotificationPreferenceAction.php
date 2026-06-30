<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Actions;

use App\Modules\Tenant\Notifications\DTOs\UpdateNotificationPreferenceData;
use App\Modules\Tenant\Notifications\Models\UserNotificationPreference;

final readonly class UpdateNotificationPreferenceAction
{
    public function execute(string $userId, UpdateNotificationPreferenceData $data): UserNotificationPreference
    {
        return UserNotificationPreference::updateOrCreate(
            [
                'tenant_id' => tenant('id'),
                'user_id' => $userId,
                'notification_key' => $data->notificationKey,
                'channel' => $data->channel,
            ],
            [
                'enabled' => $data->enabled,
            ]
        );
    }
}
