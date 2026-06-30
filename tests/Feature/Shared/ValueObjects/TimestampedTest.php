<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Timestamped;

test('timestamped creates from datetime string', function () {
    $ts = new Timestamped('2026-06-29 12:00:00');

    expect($ts->format('Y-m-d'))->toBe('2026-06-29');
});

test('timestamped creates from DateTimeImmutable', function () {
    $dt = new DateTimeImmutable('2026-06-29 12:00:00');
    $ts = new Timestamped($dt);

    expect($ts->format('Y-m-d H:i:s'))->toBe('2026-06-29 12:00:00');
});

test('timestamped now returns current time', function () {
    $ts = Timestamped::now();

    expect($ts->format('Y-m-d'))->toBe(date('Y-m-d'));
});

test('timestamped comparison', function () {
    $early = new Timestamped('2026-01-01');
    $late = new Timestamped('2026-12-31');

    expect($late->isAfter($early))->toBeTrue();
    expect($early->isBefore($late))->toBeTrue();
    expect($early->isAfter($late))->toBeFalse();
});

test('timestamped equality', function () {
    $a = new Timestamped('2026-06-29 12:00:00');
    $b = new Timestamped('2026-06-29 12:00:00');

    expect($a->equals($b))->toBeTrue();
});

test('timestamped diff in seconds', function () {
    $start = new Timestamped('2026-06-29 12:00:00');
    $end = new Timestamped('2026-06-29 12:05:30');

    expect($end->diffInSeconds($start))->toBe(330);
});

test('timestamped rejects invalid strings', function () {
    expect(fn () => new Timestamped('not-a-date'))->toThrow(\InvalidArgumentException::class);
});
