<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeTenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantName,
        public string $domain
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Welcome to LaraShift! Your account is ready.'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Great news! Your SaaS instance for **:tenant** has been successfully provisioned.', ['tenant' => $this->tenantName]))
            ->line(__('You can access your dashboard at:'))
            ->action(__('Access Dashboard', []), "http://{$this->domain}")
            ->line(__('Your initial administrator account is this email address.'))
            ->line(__('Thank you for joining our platform!'));
    }
}
