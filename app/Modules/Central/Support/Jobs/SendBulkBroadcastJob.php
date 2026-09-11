<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Jobs;

use App\Modules\Central\Support\Models\Broadcast;
use App\Modules\Central\Support\Notifications\BroadcastNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

final class SendBulkBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Broadcast $broadcast
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Audiencia desde Broadcast::recipients() (definición única).
        // Process in chunks to avoid memory issues and timeouts
        $this->broadcast->recipients()->chunkById(100, function ($tenants) {
            Notification::send($tenants, new BroadcastNotification(
                $this->broadcast->title,
                $this->broadcast->body
            ));
        });

        // Idempotent: only set sent_at if not already set (prevents double-send on retry)
        if (! $this->broadcast->sent_at) {
            $this->broadcast->update(['sent_at' => now()]);
        }
    }
}
