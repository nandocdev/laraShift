<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use Spatie\LaravelData\Data;

final class PayoutData extends Data
{
    public function __construct(
        public float $amount,
        public string $currency,
        public string $country,
        public string $tenantId,
        public string $externalId, // Internal reference for the payout
        public string $method, // e.g., 'BANK_TRANSFER', 'WALLET'
        public array $beneficiary, // Name, ID, Address, Account info
        public ?string $description = null,
        public ?string $callbackUrl = null,
        public array $customFields = [],
    ) {}
}
