<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $tenantName
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('tenant.invitations.accept', ['token' => $this->token]);

        return (new MailMessage)
            ->subject(__('You have been invited to join :tenant', ['tenant' => $this->tenantName]))
            ->greeting(__('Hello!'))
            ->line(__('You have been invited to collaborate with **:tenant** on LaraShift.', ['tenant' => $this->tenantName]))
            ->line(__('Click the button below to accept the invitation and set up your account.'))
            ->action(__('Join Team', []), $url)
            ->line(__('This invitation link will expire in 48 hours.'))
            ->line(__('If you did not expect this invitation, you can safely ignore this email.'));
    }
}
