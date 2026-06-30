<?php

declare(strict_types=1);

use App\Modules\Shared\Events\DomainEvent;
use App\Modules\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Event;

class DomainEventTestSample extends DomainEvent
{
    public function __construct(
        public string $entityId = 'test-123',
        public string $action = 'created',
        int $version = 1,
    ) {
        parent::__construct($version);
    }
}

class DomainEventTestV2 extends DomainEvent
{
    public function __construct()
    {
        parent::__construct(version: 2);
    }
}

beforeEach(function () {
    $this->event = new DomainEventTestSample;
});

test('domain event generates a valid event id', function () {
    expect(Uuid::fromString($this->event->eventId))->toBeInstanceOf(Uuid::class);
});

test('domain event generates event type from class name', function () {
    expect($this->event->eventType)->toBe('domain_event_test_sample');
});

test('domain event records occurred_at timestamp', function () {
    expect($this->event->occurredAt)->not->toBeNull();
    expect(strtotime($this->event->occurredAt))->not->toBeFalse();
});

test('domain event supports explicit versioning', function () {
    $v2 = new DomainEventTestV2;

    expect($v2->version)->toBe(2);
    expect($v2->versionString())->toBe('v2');
});

test('domain event toPayload excludes envelope metadata', function () {
    $payload = $this->event->toPayload();

    expect($payload)->toHaveKey('entityId');
    expect($payload)->toHaveKey('action');
    expect($payload)->not->toHaveKey('eventId');
    expect($payload)->not->toHaveKey('eventType');
    expect($payload)->not->toHaveKey('version');
});

test('domain event toEnvelope returns full envelope', function () {
    $this->event->tenantId = 'tenant-1';
    $this->event->correlationId = 'corr-123';

    $envelope = $this->event->toEnvelope();

    expect($envelope['event_id'])->toBe($this->event->eventId);
    expect($envelope['event_type'])->toBe($this->event->eventType);
    expect($envelope['version'])->toBe($this->event->version);
    expect($envelope['tenant_id'])->toBe('tenant-1');
    expect($envelope['correlation_id'])->toBe('corr-123');
    expect($envelope['occurred_at'])->toBe($this->event->occurredAt);
    expect($envelope['payload'])->toHaveKey('entityId');
});

test('domain event can be dispatched via Laravel', function () {
    Event::fake();

    event($this->event);

    Event::assertDispatched(DomainEventTestSample::class);
});
