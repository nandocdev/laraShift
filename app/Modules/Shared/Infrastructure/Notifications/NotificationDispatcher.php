<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Notifications\Actions\SendEmailNotificationAction;
use App\Modules\Tenant\Notifications\Actions\SendInAppNotificationAction;
use App\Modules\Tenant\Notifications\DTOs\SendNotificationData;
use App\Modules\Tenant\Notifications\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Log;

final readonly class NotificationDispatcher
{
    public function __construct(
        private SendInAppNotificationAction $inApp,
        private SendEmailNotificationAction $email,
    ) {}

    public function send(User $user, string $key, array $payload = []): void
    {
        $channels = $this->resolveChannels($user, $key);

        foreach ($channels as $channel) {
            try {
                $data = new SendNotificationData(
                    user: $user,
                    key: $key,
                    payload: $payload,
                    channel: $channel,
                );

                match ($channel) {
                    'in-app' => $this->inApp->execute($data),
                    'email' => $this->email->execute($user, $key, $payload),
                    default => Log::warning("Unknown notification channel: {$channel}"),
                };
            } catch (\Throwable $e) {
                Log::error("Failed to send notification via {$channel}", [
                    'user_id' => $user->id,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveChannels(User $user, string $key): array
    {
        $preferences = UserNotificationPreference::where('user_id', $user->id)
            ->where('notification_key', $key)
            ->get()
            ->keyBy('channel');

        $channels = [];

        $inAppPref = $preferences->get('in-app');
        if ($inAppPref === null || $inAppPref->enabled) {
            $channels[] = 'in-app';
        }

        $emailPref = $preferences->get('email');
        if ($emailPref === null || $emailPref->enabled) {
            $channels[] = 'email';
        }

        return $channels;
    }
}
