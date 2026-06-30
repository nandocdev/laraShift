<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Contracts;

use App\Modules\Shared\Events\DomainEvent;

/**
 * Contract for subscribing to and handling domain events.
 */
interface EventSubscriber
{
    /**
     * Handle a domain event.
     * Must be idempotent — called at-least-once.
     */
    public function handle(DomainEvent $event): void;

    /**
     * The event type this subscriber handles.
     */
    public function subscribedTo(): string;

    /**
     * Whether this subscriber should handle events asynchronously.
     */
    public function isAsync(): bool;
}
