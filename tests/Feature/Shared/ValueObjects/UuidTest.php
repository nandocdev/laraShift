<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Uuid;

test('uuid generates valid v7 uuid', function () {
    $uuid = Uuid::generate();

    expect($uuid->value())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

test('uuid accepts valid uuid strings', function () {
    $value = '550e8400-e29b-41d4-a716-446655440000';
    $uuid = Uuid::fromString($value);

    expect($uuid->value())->toBe($value);
});

test('uuid rejects invalid strings', function () {
    expect(fn () => new Uuid('not-a-uuid'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => new Uuid(''))->toThrow(\InvalidArgumentException::class);
});

test('uuid equality', function () {
    $a = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
    $b = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
    $c = Uuid::fromString('660e8400-e29b-41d4-a716-446655440000');

    expect($a->equals($b))->toBeTrue();
    expect($a->equals($c))->toBeFalse();
});

test('uuid generates unique values', function () {
    $a = Uuid::generate();
    $b = Uuid::generate();

    expect($a->value())->not->toBe($b->value());
});
