<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Domain\ValueObjects;

final readonly class FraudSignals
{
    /**
     * @param  array<string, mixed>  $signals  Raw signal map (signal_name => value/bool)
     */
    public function __construct(
        public readonly int $score,
        public readonly array $signals,
        public readonly string $ip,
        public readonly string $email,
    ) {}

    public function exceedsThreshold(int $threshold = 80): bool
    {
        return $this->score >= $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'ip' => $this->ip,
            'email' => $this->email,
            'signals' => $this->signals,
        ];
    }
}
