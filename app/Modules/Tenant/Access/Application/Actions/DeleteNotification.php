<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Platform\Data\Models\Notification;

final readonly class DeleteNotification
{
    public function execute(string $notificationId): void
    {
        Notification::where('id', $notificationId)
            ->where('notifiable_id', auth()->id())
            ->delete();
    }
}
