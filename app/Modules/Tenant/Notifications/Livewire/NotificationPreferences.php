<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Notifications\Livewire;

use App\Modules\Tenant\Notifications\Actions\UpdateNotificationPreferenceAction;
use App\Modules\Tenant\Notifications\DTOs\UpdateNotificationPreferenceData;
use App\Modules\Tenant\Notifications\Models\UserNotificationPreference;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class NotificationPreferences extends Component
{
    public array $preferences = [];

    public function mount(): void
    {
        $existing = UserNotificationPreference::where('user_id', auth()->id())
            ->get()
            ->keyBy(fn ($p) => "{$p->notification_key}.{$p->channel}");

        $keys = [
            'auth.login',
            'user.invited',
            'role.updated',
            'export.ready',
            'quota.warning',
            'billing.receipt',
            'system.announcement',
        ];

        $channels = ['in-app', 'email'];

        foreach ($keys as $key) {
            foreach ($channels as $channel) {
                $index = "{$key}.{$channel}";
                $pref = $existing->get($index);
                $this->preferences[$index] = $pref ? $pref->enabled : true;
            }
        }
    }

    public function save(UpdateNotificationPreferenceAction $action): void
    {
        $userId = auth()->id();

        foreach ($this->preferences as $index => $enabled) {
            [$key, $channel] = explode('.', $index, 2);

            $data = new UpdateNotificationPreferenceData(
                notificationKey: $key,
                channel: $channel,
                enabled: (bool) $enabled,
            );

            $action->execute((string) $userId, $data);
        }

        $this->dispatch('notify', message: __('Notification preferences updated.'));
    }

    public function render(): View
    {
        return view('notifications::livewire.notification-preferences');
    }
}
