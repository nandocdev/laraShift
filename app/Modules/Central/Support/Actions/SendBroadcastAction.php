<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Actions;

use App\Modules\Central\Support\DTOs\BroadcastData;
use App\Modules\Central\Support\Jobs\SendBulkBroadcastJob;
use App\Modules\Central\Support\Models\Broadcast;
use Illuminate\Support\Str;

final readonly class SendBroadcastAction
{
    /**
     * Crea el broadcast y lo envía, programa o guarda como borrador.
     * La audiencia vive en Broadcast::recipients() (definición única).
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
            'scheduled_at' => $data->scheduledAt,
            'is_draft' => $data->draft,
        ]);

        if ($data->filterType === 'selected' && $data->tenantIds !== []) {
            $broadcast->tenants()->sync($data->tenantIds);
        }

        $broadcast->update(['recipient_count' => $broadcast->recipients()->count()]);

        if ($data->draft || $this->isScheduled($broadcast)) {
            activity('support')
                ->performedOn($broadcast)
                ->log($data->draft ? 'broadcast_drafted' : 'broadcast_scheduled');

            return $broadcast;
        }

        $this->dispatch($broadcast);

        activity('support')
            ->performedOn($broadcast)
            ->log('broadcast_initiated');

        return $broadcast;
    }

    /**
     * Envía un broadcast programado vencido o un borrador publicado.
     * Idempotente: nunca reenvía lo ya enviado.
     */
    public function sendNow(Broadcast $broadcast): void
    {
        if ($broadcast->sent_at) {
            return;
        }

        $this->dispatch($broadcast);

        activity('support')
            ->performedOn($broadcast)
            ->log('broadcast_initiated');
    }

    private function dispatch(Broadcast $broadcast): void
    {
        if (in_array('email', $broadcast->channels ?? [])) {
            // Scalability: Move heavy notification sending to background job
            SendBulkBroadcastJob::dispatch($broadcast);
        } else {
            // Banners are effectively "sent" once they are in the DB with sent_at,
            // as the Tenant UI will pull them dynamically based on filters.
            $broadcast->update(['sent_at' => now()]);
        }
    }

    private function isScheduled(Broadcast $broadcast): bool
    {
        return $broadcast->scheduled_at !== null && $broadcast->scheduled_at->isFuture();
    }
}
