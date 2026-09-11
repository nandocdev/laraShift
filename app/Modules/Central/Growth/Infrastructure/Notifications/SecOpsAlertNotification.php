<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Infrastructure\Notifications;

use App\Modules\Central\Growth\Domain\ValueObjects\FraudSignals;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued notification dispatched to the SecOps team when a tenant
 * is placed in quarantine due to a high fraud score.
 *
 * Recipient configured via config('fraud.secops_email').
 */
class SecOpsAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $tenantSlug,
        public readonly FraudSignals $fraudSignals,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $signals = $this->fraudSignals->signals;
        $signalLines = array_map(
            fn (string $key, mixed $value) => "• **{$key}**: ".json_encode($value),
            array_keys($signals),
            array_values($signals),
        );

        return (new MailMessage)
            ->subject("🚨 [SecOps] Tenant quarantined — fraud score {$this->fraudSignals->score}")
            ->greeting('Fraud detection alert')
            ->line("Tenant **{$this->tenantSlug}** (`{$this->tenantId}`) was placed in quarantine during registration.")
            ->line("**Fraud score:** {$this->fraudSignals->score} / 100")
            ->line("**IP:** {$this->fraudSignals->ip}")
            ->line("**Email:** {$this->fraudSignals->email}")
            ->line('**Active signals:**')
            ->line(implode("\n", $signalLines))
            ->line('Review the tenant in the Central Dashboard and approve or permanently suspend it.')
            ->action('Review tenant', url('/central/provisioning/'.$this->tenantId.'/manage'))
            ->line('This notification was generated automatically. Do not reply.');
    }
}
