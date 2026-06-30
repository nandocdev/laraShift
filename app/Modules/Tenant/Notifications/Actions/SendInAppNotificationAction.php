<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Actions;

use App\Modules\Shared\Models\Notification;
use App\Modules\Tenant\Notifications\DTOs\SendNotificationData;
use Illuminate\Support\Str;

final readonly class SendInAppNotificationAction
{
    public function execute(SendNotificationData $data): Notification
    {
        $message = $data->payload['message'] ?? __("notification.{$data->key}.default");

        return Notification::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'notifiable_id' => $data->user->id,
            'notifiable_type' => get_class($data->user),
            'type' => "App\\Notifications\\{$data->key}",
            'data' => [
                'key' => $data->key,
                'message' => $message,
                'payload' => $data->payload,
            ],
        ]);
    }
}
