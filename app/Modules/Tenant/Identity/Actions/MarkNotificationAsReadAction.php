<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Shared\Models\Notification;

final readonly class MarkNotificationAsReadAction
{
    public function execute(string $notificationId): void
    {
        Notification::where('id', $notificationId)
            ->where('notifiable_id', auth()->id())
            ->update(['read_at' => now()]);
    }
}
