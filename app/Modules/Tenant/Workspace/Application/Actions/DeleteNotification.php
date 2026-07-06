<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Application\Actions;

use App\Modules\Tenant\Workspace\Domain\Models\Notification;

final readonly class DeleteNotification
{
    public function execute(string $notificationId): void
    {
        Notification::where('id', $notificationId)
            ->where('notifiable_id', auth()->id())
            ->delete();
    }
}
