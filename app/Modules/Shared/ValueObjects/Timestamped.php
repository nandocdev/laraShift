<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Timestamped
{
    private DateTimeImmutable $value;

    public function __construct(DateTimeImmutable|string $value)
    {
        if (is_string($value)) {
            $parsed = CarbonImmutable::parse($value);

            if (! $parsed instanceof DateTimeImmutable) {
                throw new InvalidArgumentException("Invalid timestamp: {$value}");
            }

            $this->value = $parsed;
        } else {
            $this->value = $value;
        }
    }

    public static function now(): self
    {
        return new self(new DateTimeImmutable);
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function format(string $format = 'Y-m-d\TH:i:s.uP'): string
    {
        return $this->value->format($format);
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function diffInSeconds(self $other): int
    {
        return $this->value->getTimestamp() - $other->value->getTimestamp();
    }

    public function equals(self $other): bool
    {
        return (string) $this->value === (string) $other->value;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
