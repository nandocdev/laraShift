<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    private const array SUPPORTED_CURRENCIES = ['USD', 'EUR', 'GBP', 'MXN', 'COP', 'BRL', 'PEN', 'CLP', 'ARS'];

    private int $amount;
    private string $currency;

    public function __construct(int|float|string $amount, string $currency = 'USD')
    {
        $currency = strtoupper($currency);

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new InvalidArgumentException("Unsupported currency: {$currency}");
        }

        $this->currency = $currency;
        $this->amount = (int) round((float) $amount);
    }

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, $currency);
    }

    public static function fromDecimal(float $decimal, string $currency = 'USD'): self
    {
        return new self((int) round($decimal * 100), $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function toDecimal(): float
    {
        return $this->amount / 100;
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add Money with different currencies');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot subtract Money with different currencies');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function __toString(): string
    {
        return number_format($this->toDecimal(), 2) . " {$this->currency}";
    }
}
