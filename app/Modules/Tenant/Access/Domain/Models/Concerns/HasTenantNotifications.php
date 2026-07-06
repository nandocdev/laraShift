<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Domain\Models\Concerns;

use App\Modules\Tenant\Workspace\Domain\Models\Notification;

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
