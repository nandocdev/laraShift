<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Dlq;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class RetryDeadLetterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    public int $timeout = 120;

    public function handle(): void
    {
        $batchSize = config('events.dlq.batch_size', 20);

        DeadLetterEvent::retryable()
            ->orderBy('created_at')
            ->limit($batchSize)
            ->chunkById(50, function ($events) {
                foreach ($events as $dlq) {
                    try {
                        $eventClass = $this->resolveEventClass($dlq->event_type);

                        if ($eventClass === null) {
                            $dlq->markFailed('No handler registered for event type: '.$dlq->event_type);

                            continue;
                        }

                        event(new $eventClass(...$dlq->payload));

                        $dlq->markResolved();
                    } catch (\Throwable $e) {
                        Log::error('DLQ retry failed', [
                            'event_id' => $dlq->id,
                            'event_type' => $dlq->event_type,
                            'retry' => $dlq->retry_count,
                            'error' => $e->getMessage(),
                        ]);

                        if ($dlq->isExhausted()) {
                            Log::critical('DLQ event exhausted all retries', [
                                'event_id' => $dlq->id,
                                'event_type' => $dlq->event_type,
                                'retries' => $dlq->retry_count,
                            ]);
                        }

                        $dlq->markFailed($e->getMessage());
                    }
                }
            });
    }

    private function resolveEventClass(string $eventType): ?string
    {
        $map = config('events.map', []);

        return $map[$eventType] ?? null;
    }
}
