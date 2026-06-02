<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Models\Broadcast;
use App\Modules\Central\Support\Notifications\BroadcastNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final readonly class SendBroadcastAction
{
    /**
     * Sends a broadcast message to multiple tenants based on filters.
     */
    public function execute(
        string $title,
        string $body,
        string $filterType = 'all',
        ?string $filterValue = null,
        array $channels = ['email']
    ): Broadcast {
        $broadcast = Broadcast::create([
            'id' => Str::uuid()->toString(),
            'created_by' => auth('central')->id(),
            'title' => $title,
            'body' => $body,
            'filter_type' => $filterType,
            'filter_value' => $filterValue,
            'channels' => $channels,
        ]);

        $query = Tenant::query();

        if ($filterType === 'plan' && $filterValue) {
            $query->where('plan_id', $filterValue);
        } elseif ($filterType === 'status' && $filterValue) {
            $query->where('status', $filterValue);
        }

        $tenants = $query->get();
        $broadcast->update(['recipient_count' => $tenants->count()]);

        if (in_array('email', $channels)) {
            // Note: In large scales, this should be a chunked background job
            Notification::send($tenants, new BroadcastNotification($title, $body));
        }

        $broadcast->update(['sent_at' => now()]);

        activity('support')
            ->performedOn($broadcast)
            ->log('broadcast_sent');

        return $broadcast;
    }
}
