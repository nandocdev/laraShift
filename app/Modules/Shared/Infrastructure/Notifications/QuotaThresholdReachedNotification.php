<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaThresholdReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $metric,
        public int $current,
        public int $limit,
        public int $threshold // 80 or 100
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $metricName = ucfirst(str_replace('_', ' ', $this->metric));
        
        $message = (new MailMessage)
            ->subject(__(':metric Quota Warning (:threshold%)', ['metric' => $metricName, 'threshold' => $this->threshold]))
            ->greeting(__('Hello!'));

        if ($this->threshold === 100) {
            $message->error()
                ->line(__('Your organization has reached **100%** of its :metric quota.', ['metric' => $metricName]))
                ->line(__('Current usage: **:current / :limit**', ['current' => $this->current, 'limit' => $this->limit]))
                ->line(__('To prevent service interruption, please consider upgrading your plan.'))
                ->action(__('View Plans', []), route('tenant.billing.manage'));
        } else {
            $message->line(__('Your organization has reached **:threshold%** of its :metric quota.', ['metric' => $metricName, 'threshold' => $this->threshold]))
                ->line(__('Current usage: **:current / :limit**', ['current' => $this->current, 'limit' => $this->limit]))
                ->line(__('This is just a friendly reminder to monitor your usage.'))
                ->action(__('Dashboard', []), route('dashboard'));
        }

        return $message;
    }
}
