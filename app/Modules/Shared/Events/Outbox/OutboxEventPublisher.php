<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Outbox;

use App\Modules\Shared\Events\Contracts\EventPublisher;
use App\Modules\Shared\Events\DomainEvent;
use App\Modules\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\DB;

final readonly class OutboxEventPublisher implements EventPublisher
{
    public function publish(DomainEvent $event): string
    {
        $envelope = $event->toEnvelope();

        DB::transaction(function () use ($envelope) {
            OutboxEvent::create([
                'id' => $envelope['event_id'],
                'event_type' => $envelope['event_type'],
                'version' => $envelope['version'],
                'tenant_id' => $envelope['tenant_id'],
                'correlation_id' => $envelope['correlation_id'],
                'causer_id' => $envelope['causer_id'],
                'causer_type' => $envelope['causer_type'],
                'payload' => $envelope['payload'],
                'status' => 'pending',
            ]);
        });

        return $envelope['event_id'];
    }

    public function publishBatch(array $events): array
    {
        $ids = [];

        DB::transaction(function () use ($events, &$ids) {
            foreach ($events as $event) {
                $ids[] = $this->publish($event);
            }
        });

        return $ids;
    }

    public function publishAfter(DomainEvent $event, \DateTimeInterface $at): string
    {
        $envelope = $event->toEnvelope();

        DB::transaction(function () use ($envelope, $at) {
            OutboxEvent::create([
                'id' => $envelope['event_id'],
                'event_type' => $envelope['event_type'],
                'version' => $envelope['version'],
                'tenant_id' => $envelope['tenant_id'],
                'correlation_id' => $envelope['correlation_id'],
                'causer_id' => $envelope['causer_id'],
                'causer_type' => $envelope['causer_type'],
                'payload' => $envelope['payload'],
                'status' => 'pending',
                'available_at' => $at,
            ]);
        });

        return $envelope['event_id'];
    }
}
