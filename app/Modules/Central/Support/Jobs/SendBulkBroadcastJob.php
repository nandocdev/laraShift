<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Jobs;

use App\Modules\Central\Provisioning\Models\Tenant;
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
        $query = Tenant::query();

        if ($this->broadcast->filter_type === 'plan' && $this->broadcast->filter_value) {
            $query->where('plan_id', $this->broadcast->filter_value);
        } elseif ($this->broadcast->filter_type === 'status' && $this->broadcast->filter_value) {
            $query->where('status', $this->broadcast->filter_value);
        }

        // Process in chunks to avoid memory issues and timeouts
        $query->chunk(100, function ($tenants) {
            Notification::send($tenants, new BroadcastNotification(
                $this->broadcast->title,
                $this->broadcast->body
            ));
        });

        $this->broadcast->update(['sent_at' => now()]);
    }
}
