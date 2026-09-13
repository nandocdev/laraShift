<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OpsIncidentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{id: string, severity: string, title: string, detail: string, link: string|null}>  $incidents
     */
    public function __construct(
        public readonly array $incidents
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->incidents);

        $mail = (new MailMessage)
            ->subject($count === 1 ? '[Ops] 1 critical platform incident' : "[Ops] {$count} critical platform incidents")
            ->line('Automatic platform watch detected critical incidents requiring attention:');

        foreach ($this->incidents as $incident) {
            $mail->line("• {$incident['title']}: {$incident['detail']}");
        }

        if (($this->incidents[0]['link'] ?? null) !== null) {
            $mail->action('Open Health Monitor', route('central.health.monitor'));
        }

        return $mail;
    }
}
