<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalCheckoutLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $checkoutUrl,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Renew your subscription'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Your billing period ends soon. Complete your renewal before it expires.'))
            ->action(__('Renew now', []), $this->checkoutUrl);

        if ($this->expiresAt) {
            $message->line(__('This link expires on :date.', ['date' => $this->expiresAt->toDateTimeString()]));
        }

        return $message;
    }
}
