<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantName,
        public string $domain,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your LaraShift onboarding has expired'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your instance for **:tenant** was created but the payment was not completed, so the onboarding has expired.', ['tenant' => $this->tenantName]))
            ->line(__('If you still want to use the platform, you can start a new onboarding at any time.'))
            ->action(__('Return to LaraShift', []), "http://{$this->domain}");
    }
}
