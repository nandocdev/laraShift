<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AuditLogExportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $filePath
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Generate a signed URL that expires in 24 hours
        $url = URL::temporarySignedRoute(
            'tenant.audit.download',
            now()->addHours(24),
            ['path' => $this->filePath]
        );

        return (new MailMessage)
            ->subject(__('Your Audit Log Export is ready'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The audit log export you requested has been generated successfully.'))
            ->line(__('You can download it using the button below. Note: This link will expire in 24 hours.'))
            ->action(__('Download CSV', []), $url)
            ->line(__('Thank you for using LaraShift!'));
    }
}
