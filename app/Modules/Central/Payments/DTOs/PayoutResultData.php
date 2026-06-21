<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use Spatie\LaravelData\Data;

final class PayoutResultData extends Data
{
    public function __construct(
        public string $id,
        public string $status, // PENDING, PAID, REJECTED
        public float $amount,
        public string $currency,
        public ?string $statusDetail = null,
        public ?string $errorCode = null,
        public array $raw = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'PAID';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }
}
