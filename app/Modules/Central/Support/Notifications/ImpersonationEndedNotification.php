<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImpersonationEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $reason,
        public string $startedAt
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Security Notice: Administrative access session ended'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('This is an automated notification to inform you that an administrative support session on your account has concluded.'))
            ->line(__('Access Details:'))
            ->line(__('Reason: :reason', ['reason' => $this->reason]))
            ->line(__('Started at: :date', ['date' => $this->startedAt]))
            ->line(__('If you have any questions regarding this access, please contact our support team.'));
    }
}
