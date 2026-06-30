<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Outbox;

use App\Modules\Shared\Events\Dlq\DeadLetterEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class PublishOutboxEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    public int $timeout = 120;

    public function handle(): void
    {
        $batchSize = config('events.outbox.batch_size', 50);

        OutboxEvent::pending()
            ->orderBy('created_at')
            ->limit($batchSize)
            ->chunkById(50, function ($events) {
                foreach ($events as $outbox) {
                    try {
                        $eventClass = $this->resolveEventClass($outbox->event_type);

                        if ($eventClass === null) {
                            $outbox->markFailed('No handler registered for event type: ' . $outbox->event_type);
                            $this->sendToDlq($outbox);
                            continue;
                        }

                        event(new $eventClass(...$outbox->payload));

                        $outbox->markPublished();
                    } catch (\Throwable $e) {
                        Log::error('Outbox publish failed', [
                            'event_id' => $outbox->id,
                            'event_type' => $outbox->event_type,
                            'error' => $e->getMessage(),
                        ]);

                        $outbox->markFailed($e->getMessage());

                        if ($outbox->retry_count >= config('events.outbox.max_retries', 5)) {
                            $this->sendToDlq($outbox);
                        }
                    }
                }
            });
    }

    private function resolveEventClass(string $eventType): ?string
    {
        $map = config('events.map', []);

        return $map[$eventType] ?? null;
    }

    private function sendToDlq(OutboxEvent $outbox): void
    {
        DeadLetterEvent::create([
            'outbox_event_id' => $outbox->id,
            'event_type' => $outbox->event_type,
            'version' => $outbox->version,
            'tenant_id' => $outbox->tenant_id,
            'correlation_id' => $outbox->correlation_id,
            'payload' => $outbox->payload,
            'error_log' => [['error' => $outbox->last_error, 'at' => $outbox->last_error_at?->toIso8601String()]],
            'retry_count' => 0,
            'max_retries' => config('events.dlq.max_retries', 5),
            'status' => 'failed',
            'last_attempt_at' => now(),
            'next_retry_at' => now()->addMinute(),
        ]);

        $outbox->update(['status' => 'dead_letter']);
    }
}
