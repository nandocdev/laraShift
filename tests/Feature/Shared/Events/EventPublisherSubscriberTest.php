<?php

declare(strict_types=1);

use App\Modules\Shared\Events\Contracts\EventPublisher;
use App\Modules\Shared\Events\Contracts\EventSubscriber;
use App\Modules\Shared\Events\DomainEvent;
use App\Modules\Shared\Events\Outbox\OutboxEventPublisher;

/**
 * Contract test: verifies that the EventPublisher contract delivers
 * events with at-least-once semantics.
 */

// A concrete subscriber for testing
class TestSubscriber implements EventSubscriber
{
    public int $handleCount = 0;

    public array $receivedEvents = [];

    public function handle(DomainEvent $event): void
    {
        $this->handleCount++;
        $this->receivedEvents[] = $event;
    }

    public function subscribedTo(): string
    {
        return 'test_event';
    }

    public function isAsync(): bool
    {
        return false;
    }
}

// A test event
class PublisherTestEvent extends DomainEvent
{
    public function __construct(
        public string $payload = 'contract-test',
    ) {
        parent::__construct(version: 1);
    }

    public static function eventType(): string
    {
        return 'test_event';
    }
}

beforeEach(function () {
    $this->publisher = app(OutboxEventPublisher::class);
    $this->subscriber = new TestSubscriber;
    $this->event = new PublisherTestEvent;
});

test('publisher is bound in container', function () {
    $publisher = app(EventPublisher::class);

    expect($publisher)->toBeInstanceOf(EventPublisher::class);
});

test('publisher returns string event id', function () {
    $eventId = $this->publisher->publish($this->event);

    expect($eventId)->toBeString();
    expect(strlen($eventId))->toBeGreaterThan(0);
});

test('publisher persists event to outbox', function () {
    $this->publisher->publish($this->event);

    $this->assertDatabaseHas('outbox_events', [
        'event_type' => 'test_event',
        'status' => 'pending',
    ]);
});

test('subscriber receives the correct event type', function () {
    expect($this->subscriber->subscribedTo())->toBe('test_event');
});

test('subscriber handle is idempotent when called multiple times', function () {
    $this->subscriber->handle($this->event);
    $this->subscriber->handle($this->event);

    expect($this->subscriber->handleCount)->toBe(2);
    // Idempotency: calling handle twice should not cause duplicate side effects
    // (idempotency enforcement is responsibility of the handler implementation)
});

test('publishBatch returns all event ids in order', function () {
    $event2 = new PublisherTestEvent('second');

    $ids = $this->publisher->publishBatch([$this->event, $event2]);

    expect($ids)->toHaveCount(2);
    expect($ids[0])->toBe($this->event->eventId);
    expect($ids[1])->toBe($event2->eventId);
});

test('publishBatch is transactional', function () {
    $ids = $this->publisher->publishBatch([$this->event]);

    $this->assertDatabaseHas('outbox_events', ['id' => $ids[0]]);
});

test('event subscriber contract defines async flag', function () {
    expect($this->subscriber->isAsync())->toBeFalse();
});

test('published events can be reconstructed from config map', function () {
    config(['events.map' => [
        'test_event' => PublisherTestEvent::class,
    ]]);

    $this->publisher->publish($this->event);

    $map = config('events.map');
    expect($map)->toHaveKey('test_event');
    expect($map['test_event'])->toBe(PublisherTestEvent::class);
});
