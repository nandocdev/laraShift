<?php

declare(strict_types=1);

use App\Modules\Shared\Events\DomainEvent;
use App\Modules\Shared\Events\Outbox\OutboxEvent;
use App\Modules\Shared\Events\Outbox\OutboxEventPublisher;
use App\Modules\Shared\Events\Outbox\PublishOutboxEventsJob;
use App\Modules\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Event;

class OutboxTestEvent extends DomainEvent
{
    public function __construct(
        public string $data = 'test-payload',
        ?string $eventId = null,
    ) {
        parent::__construct(version: 1, eventId: $eventId);
    }

    public static function eventType(): string
    {
        return 'outbox_test_event';
    }
}

beforeEach(function () {
    $this->publisher = app(OutboxEventPublisher::class);

    $this->testEvent = new OutboxTestEvent;
    $this->testEvent->tenantId = 'tenant-1';
    $this->testEvent->correlationId = 'corr-outbox-test';
});

test('publisher writes event to outbox table', function () {
    $eventId = $this->publisher->publish($this->testEvent);

    $this->assertDatabaseHas('outbox_events', [
        'id' => $eventId,
        'event_type' => 'outbox_test_event',
        'tenant_id' => 'tenant-1',
        'status' => 'pending',
    ]);
});

test('publisher returns event id for tracking', function () {
    $eventId = $this->publisher->publish($this->testEvent);

    expect($eventId)->toBe($this->testEvent->eventId);
});

test('publishBatch writes multiple events', function () {
    $event2Id = Uuid::generate()->value();
    $event2 = new OutboxTestEvent('second-event', $event2Id);
    $event2->tenantId = 'tenant-1';
    $event2->correlationId = 'corr-outbox-test-2';

    $ids = $this->publisher->publishBatch([$this->testEvent, $event2]);

    expect($ids)->toHaveCount(2);
    expect($ids[0])->toBe($this->testEvent->eventId);
    expect($ids[1])->toBe($event2Id);

    $this->assertDatabaseHas('outbox_events', ['id' => $ids[0], 'status' => 'pending']);
    $this->assertDatabaseHas('outbox_events', ['id' => $ids[1], 'status' => 'pending']);
});

test('publishAfter schedules event for future', function () {
    $future = now()->addHour();
    $eventId = $this->publisher->publishAfter($this->testEvent, $future);

    $this->assertDatabaseHas('outbox_events', [
        'id' => $eventId,
        'status' => 'pending',
    ]);

    $outbox = OutboxEvent::find($eventId);
    expect($outbox->available_at->timestamp)->toBe($future->timestamp);
});

test('pending scope returns only publishable events', function () {
    $this->publisher->publish($this->testEvent);

    $futureEvent = new OutboxTestEvent('future-payload');
    $futureEvent->tenantId = 'tenant-1';
    $futureId = $this->publisher->publishAfter($futureEvent, now()->addDay());

    $pending = OutboxEvent::pending()->get();

    expect($pending->count())->toBe(1);
    expect($pending->pluck('id'))->not->toContain($futureId);
});

test('outbox job publishes events and marks as published', function () {
    Event::fake();

    config(['events.map' => ['outbox_test_event' => OutboxTestEvent::class]]);

    $this->publisher->publish($this->testEvent);

    $job = new PublishOutboxEventsJob;
    $job->handle();

    $this->assertDatabaseHas('outbox_events', [
        'id' => $this->testEvent->eventId,
        'status' => 'published',
    ]);

    expect(OutboxEvent::pending()->count())->toBe(0);
});

test('outbox job respects event map config', function () {
    config(['events.map' => ['outbox_test_event' => OutboxTestEvent::class]]);

    $this->publisher->publish($this->testEvent);

    $job = new PublishOutboxEventsJob;
    $job->handle();

    $this->assertDatabaseHas('outbox_events', [
        'id' => $this->testEvent->eventId,
        'status' => 'published',
    ]);
});

test('outbox job sends unhandled events to dead letter', function () {
    config(['events.map' => []]);

    $this->publisher->publish($this->testEvent);

    $job = new PublishOutboxEventsJob;
    $job->handle();

    $this->assertDatabaseHas('outbox_events', [
        'id' => $this->testEvent->eventId,
        'status' => 'dead_letter',
    ]);

    $this->assertDatabaseHas('dead_letter_events', [
        'event_type' => 'outbox_test_event',
    ]);
});

test('outbox retry count stays zero on success', function () {
    config(['events.map' => ['outbox_test_event' => OutboxTestEvent::class]]);

    $this->publisher->publish($this->testEvent);

    $job = new PublishOutboxEventsJob;
    $job->handle();

    $outbox = OutboxEvent::find($this->testEvent->eventId);
    expect($outbox->retry_count)->toBe(0);
    expect($outbox->status)->toBe('published');
});
