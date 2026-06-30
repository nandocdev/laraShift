<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Email;

test('email accepts valid addresses', function () {
    $email = new Email('user@example.com');
    expect($email->value())->toBe('user@example.com');
});

test('email normalizes to lowercase', function () {
    $email = new Email('USER@Example.COM');
    expect($email->value())->toBe('user@example.com');
});

test('email trims whitespace', function () {
    $email = new Email('  user@example.com  ');
    expect($email->value())->toBe('user@example.com');
});

test('email rejects invalid addresses', function () {
    expect(fn () => new Email('not-an-email'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => new Email('user@'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => new Email('@domain.com'))->toThrow(\InvalidArgumentException::class);
    expect(fn () => new Email(''))->toThrow(\InvalidArgumentException::class);
});

test('email extracts domain and local part', function () {
    $email = new Email('john.doe@example.com');

    expect($email->domain())->toBe('example.com');
    expect($email->localPart())->toBe('john.doe');
});

test('email equality', function () {
    $a = new Email('user@example.com');
    $b = new Email('user@example.com');
    $c = new Email('other@example.com');

    expect($a->equals($b))->toBeTrue();
    expect($a->equals($c))->toBeFalse();
});

test('email string casting', function () {
    $email = new Email('user@example.com');
    expect((string) $email)->toBe('user@example.com');
});
