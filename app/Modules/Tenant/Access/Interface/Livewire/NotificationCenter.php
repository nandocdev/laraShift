<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Livewire;

use App\Modules\Tenant\Access\Application\Actions\MarkNotificationAsRead;
use App\Modules\Tenant\Access\Application\Actions\DeleteNotification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationCenter extends Component
{
    public function markAsRead(string $notificationId, MarkNotificationAsRead $action): void
    {
        $action->execute($notificationId);
    }

    public function delete(string $notificationId, DeleteNotification $action): void
    {
        $action->execute($notificationId);
    }

    public function render(): View
    {
        return view('identity::livewire.notification-center', [
            'notifications' => auth()->user()->tenantNotifications()->paginate(10),
        ]);
    }
}
