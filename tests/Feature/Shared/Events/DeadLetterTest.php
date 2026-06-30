<?php

declare(strict_types=1);

use App\Modules\Shared\Events\Dlq\DeadLetterEvent;
use App\Modules\Shared\Events\Dlq\RetryDeadLetterJob;
use App\Modules\Shared\Events\DomainEvent;

test('dlq stores failed event metadata', function () {
    $dlq = DeadLetterEvent::create([
        'event_type' => 'test_event',
        'version' => 1,
        'tenant_id' => 'tenant-1',
        'payload' => ['key' => 'value'],
        'error_log' => [['error' => 'Connection timeout', 'at' => now()->toIso8601String()]],
        'retry_count' => 0,
        'max_retries' => 5,
        'status' => 'failed',
        'last_attempt_at' => now(),
    ]);

    expect($dlq->event_type)->toBe('test_event');
    expect($dlq->retry_count)->toBe(0);
    expect($dlq->isExhausted())->toBeFalse();
});

test('dlq marks exhausted after max retries', function () {
    $dlq = DeadLetterEvent::create([
        'event_type' => 'test_event',
        'version' => 1,
        'payload' => [],
        'retry_count' => 5,
        'max_retries' => 5,
        'status' => 'failed',
    ]);

    expect($dlq->isExhausted())->toBeTrue();
});

test('dlq retryable scope returns only eligible events', function () {
    DeadLetterEvent::create([
        'event_type' => 'retryable_event',
        'version' => 1,
        'tenant_id' => 'tenant-1',
        'payload' => [],
        'retry_count' => 1,
        'max_retries' => 5,
        'status' => 'failed',
        'next_retry_at' => now()->subMinute(),
    ]);

    DeadLetterEvent::create([
        'event_type' => 'exhausted_event',
        'version' => 1,
        'tenant_id' => 'tenant-2',
        'payload' => [],
        'retry_count' => 5,
        'max_retries' => 5,
        'status' => 'failed',
    ]);

    DeadLetterEvent::create([
        'event_type' => 'future_event',
        'version' => 1,
        'tenant_id' => 'tenant-3',
        'payload' => [],
        'retry_count' => 1,
        'max_retries' => 5,
        'status' => 'failed',
        'next_retry_at' => now()->addHour(),
    ]);

    $retryable = DeadLetterEvent::retryable()->get();

    expect($retryable)->toHaveCount(1);
    expect($retryable->first()->event_type)->toBe('retryable_event');
});

class DlqTestEvent extends DomainEvent
{
    public function __construct()
    {
        parent::__construct(1);
    }
}

test('dlq retry job processes retryable events', function () {
    Event::fake();

    $dlq = DeadLetterEvent::create([
        'event_type' => 'dlq_test_event',
        'version' => 1,
        'tenant_id' => 'tenant-1',
        'payload' => [],
        'retry_count' => 0,
        'max_retries' => 3,
        'status' => 'failed',
        'next_retry_at' => now()->subMinute(),
    ]);

    config(['events.map' => ['dlq_test_event' => DlqTestEvent::class]]);

    $job = new RetryDeadLetterJob;
    $job->handle();

    $dlq->refresh();
    expect($dlq->status)->toBe('resolved');
});

test('dlq retry increments counter on failure', function () {
    config(['events.map' => []]);

    $dlq = DeadLetterEvent::create([
        'event_type' => 'unregistered_event',
        'version' => 1,
        'tenant_id' => 'tenant-1',
        'payload' => [],
        'retry_count' => 0,
        'max_retries' => 5,
        'status' => 'failed',
        'next_retry_at' => now()->subMinute(),
    ]);

    $job = new RetryDeadLetterJob;
    $job->handle();

    $dlq->refresh();
    expect($dlq->retry_count)->toBe(1);
    expect($dlq->status)->toBe('failed');
});

test('calculateNextRetry uses exponential backoff', function () {
    $dlq = new DeadLetterEvent;
    $dlq->retry_count = 0;
    $dlq->max_retries = 5;

    $reflection = new ReflectionMethod($dlq, 'calculateNextRetry');
    $reflection->setAccessible(true);

    $firstDelay = $reflection->invoke($dlq);
    expect($firstDelay)->not->toBeNull();

    $dlq->retry_count = 4;
    $longDelay = $reflection->invoke($dlq);
    expect($longDelay)->not->toBeNull();

    $dlq->retry_count = 5;
    $exhausted = $reflection->invoke($dlq);
    expect($exhausted)->toBeNull();
});
