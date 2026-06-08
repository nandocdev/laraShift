<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Tenant\Identity\Actions\MarkNotificationAsReadAction;
use App\Modules\Tenant\Identity\Actions\DeleteNotificationAction;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationCenter extends Component
{
    public function markAsRead(string $notificationId, MarkNotificationAsReadAction $action): void
    {
        $action->execute($notificationId);
    }

    public function delete(string $notificationId, DeleteNotificationAction $action): void
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
