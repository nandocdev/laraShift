<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\DTOs\BroadcastData;
use App\Modules\Central\Support\Jobs\SendBulkBroadcastJob;
use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Support\Str;

final readonly class SendBroadcastAction
{
    /**
     * Sends a broadcast message to multiple tenants based on filters.
     * Uses a background job for scalability.
     */
    public function execute(BroadcastData $data): Broadcast
    {
        $broadcast = Broadcast::create([
            'id' => Str::uuid()->toString(),
            'created_by' => auth('central')->id(),
            'title' => $data->title,
            'body' => $data->body,
            'filter_type' => $data->filterType,
            'filter_value' => $data->filterValue,
            'channels' => $data->channels,
        ]);

        $query = Tenant::query();

        if ($data->filterType === 'plan' && $data->filterValue) {
            $query->where('plan_id', $data->filterValue);
        } elseif ($data->filterType === 'status' && $data->filterValue) {
            $query->where('status', $data->filterValue);
        }

        $broadcast->update(['recipient_count' => $query->count()]);

        if (in_array('email', $data->channels)) {
            // Scalability: Move heavy notification sending to background job
            SendBulkBroadcastJob::dispatch($broadcast);
        } else {
            // Banners are effectively "sent" once they are in the DB with sent_at,
            // as the Tenant UI will pull them dynamically based on filters.
            $broadcast->update(['sent_at' => now()]);
        }

        activity('support')
            ->performedOn($broadcast)
            ->log('broadcast_initiated');

        return $broadcast;
    }
}
