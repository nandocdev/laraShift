<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Billing period identified by 'Y-m' (e.g. '2026-08').
 */
final readonly class UsagePeriod
{
    public function __construct(
        public string $period,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * @throws InvalidArgumentException When the period does not match 'Y-m'.
     */
    public static function from(string $period): self
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $period);

        if ($start === false) {
            throw new InvalidArgumentException("Invalid usage period [{$period}]. Expected format YYYY-MM.");
        }

        $start = $start->startOfMonth();

        return new self($period, $start, $start->addMonth());
    }

    public static function current(): self
    {
        return self::from(now()->format('Y-m'));
    }

    public static function fromDate(CarbonInterface $date): self
    {
        return self::from($date->format('Y-m'));
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->between($this->start, $this->end->subSecond());
    }
}
