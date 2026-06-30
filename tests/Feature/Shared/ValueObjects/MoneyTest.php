<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Money;

test('money can be created from cents', function () {
    $money = Money::fromCents(2999, 'USD');

    expect($money->amount())->toBe(2999);
    expect($money->toDecimal())->toBe(29.99);
    expect($money->currency())->toBe('USD');
});

test('money can be created from decimal', function () {
    $money = Money::fromDecimal(29.99, 'USD');

    expect($money->amount())->toBe(2999);
    expect($money->toDecimal())->toBe(29.99);
});

test('money supports arithmetic', function () {
    $a = Money::fromDecimal(10.00, 'USD');
    $b = Money::fromDecimal(5.50, 'USD');

    expect($a->add($b)->toDecimal())->toBe(15.50);
    expect($a->subtract($b)->toDecimal())->toBe(4.50);
    expect($a->multiply(3)->toDecimal())->toBe(30.00);
});

test('money comparison works', function () {
    $big = Money::fromDecimal(100.00, 'USD');
    $small = Money::fromDecimal(50.00, 'USD');

    expect($big->isGreaterThan($small))->toBeTrue();
    expect($small->isLessThan($big))->toBeTrue();
    expect($big->equals($big))->toBeTrue();
    expect($big->isGreaterThan($big))->toBeFalse();
});

test('money rejects different currency arithmetic', function () {
    $usd = Money::fromDecimal(10, 'USD');
    $eur = Money::fromDecimal(10, 'EUR');

    expect(fn () => $usd->add($eur))->toThrow(InvalidArgumentException::class);
    expect(fn () => $usd->subtract($eur))->toThrow(InvalidArgumentException::class);
});

test('money rejects unsupported currencies', function () {
    expect(fn () => new Money(100, 'XYZ'))->toThrow(InvalidArgumentException::class);
});

test('money is zero', function () {
    $zero = Money::fromDecimal(0, 'USD');
    expect($zero->isZero())->toBeTrue();

    $nonZero = Money::fromDecimal(1, 'USD');
    expect($nonZero->isZero())->toBeFalse();
});

test('money string representation', function () {
    $money = Money::fromDecimal(29.99, 'USD');
    expect((string) $money)->toContain('29.99', 'USD');
});
