<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $attemptCount,
        public string $amount,
        public string $currency
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysUntilNextAttempt = $this->attemptCount === 1 ? 3 : 5;
        $isWarning = $this->attemptCount >= 2;

        $message = (new MailMessage)
            ->subject($isWarning ? __('URGENT: Payment failure for your subscription') : __('Payment failure for your subscription'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We were unable to process the payment of :amount :currency for your subscription.', [
                'amount' => $this->amount,
                'currency' => strtoupper($this->currency)
            ]));

        if ($isWarning) {
            $message->line(__('This is your second failed attempt. To avoid service suspension, please update your payment method.'));
        }

        $message->line(__('We will try again in :days days.', ['days' => $daysUntilNextAttempt]))
            ->action(__('Update Payment Method', []), route('central.billing.subscriptions')) // Adjust route if needed for tenant
            ->line(__('If you have already resolved this, please ignore this email.'));

        return $message;
    }
}
