<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events\Concerns;

use App\Modules\Shared\Events\Contracts\EventPublisher;
use App\Modules\Shared\Events\DomainEvent;
use Illuminate\Support\Facades\App;

/**
 * Trait for action classes that emit domain events.
 * Automatically publishes through the EventPublisher.
 */
trait RecordsEvents
{
    /** @var DomainEvent[] */
    private array $recordedEvents = [];

    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    protected function publishEvents(): void
    {
        if (empty($this->recordedEvents)) {
            return;
        }

        $publisher = App::make(EventPublisher::class);

        foreach ($this->recordedEvents as $event) {
            $publisher->publish($event);
            DomainEvent::dispatch($event);
        }

        $this->recordedEvents = [];
    }

    /**
     * @return DomainEvent[]
     */
    protected function getRecordedEvents(): array
    {
        return $this->recordedEvents;
    }

    protected function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }
}
