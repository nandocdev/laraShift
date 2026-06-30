<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Contracts;

use App\Modules\Shared\Events\DomainEvent;

/**
 * Contract for publishing domain events with at-least-once delivery.
 */
interface EventPublisher
{
    /**
     * Publish a domain event.
     * Returns the event ID for tracking and idempotency.
     */
    public function publish(DomainEvent $event): string;

    /**
     * Publish multiple domain events in a batch.
     *
     * @param DomainEvent[] $events
     * @return string[] Event IDs
     */
    public function publishBatch(array $events): array;

    /**
     * Schedule a domain event for future publication.
     */
    public function publishAfter(DomainEvent $event, \DateTimeInterface $at): string;
}
