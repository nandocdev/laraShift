<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Models\Concerns;

use App\Modules\Shared\Models\Notification;

trait HasTenantNotifications
{
    /**
     * Get the tenant-scoped notifications for the user.
     */
    public function tenantNotifications()
    {
        return $this->morphMany(Notification::class, 'notifiable')
            ->latest();
    }

    /**
     * Get the unread notifications for the user.
     */
    public function unreadTenantNotifications()
    {
        return $this->tenantNotifications()->whereNull('read_at');
    }
}
