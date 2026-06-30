<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $amount,
        public string $currency
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('Your subscription has been suspended'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your subscription has been suspended due to multiple failed payment attempts for the amount of :amount :currency.', [
                'amount' => $this->amount,
                'currency' => strtoupper($this->currency),
            ]))
            ->line(__('Your access to the platform has been restricted until the outstanding balance is cleared.'))
            ->action(__('Pay Outstanding Balance', []), route('central.billing.subscriptions'))
            ->line(__('Once the payment is processed, your access will be restored automatically.'));
    }
}
