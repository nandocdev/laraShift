<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyRotatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $keyType,
        public string $tenantName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Security: :keyType key rotated for :tenant', [
                'keyType' => ucfirst($this->keyType),
                'tenant' => $this->tenantName,
            ]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The :keyType encryption key for tenant **:tenant** has been automatically rotated as part of our security policy.', [
                'keyType' => $this->keyType,
                'tenant' => $this->tenantName,
            ]))
            ->line(__('This is a routine security measure. No action is required on your part.'))
            ->action(__('View Security Settings', []), route('central.security.policies'))
            ->line(__('If you have any questions, please contact the support team.'));
    }
}
