<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use Spatie\LaravelData\Data;

final class ServiceData extends Data
{
    public function __construct(
        public readonly string $idMerchantService,
        public readonly string $gatewayCode,
        public readonly float $txLimit,
        public readonly float $dailyAmountLimit,
        public readonly float $monthlyAmountLimit,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            idMerchantService: (string) $data['idMerchantService'],
            gatewayCode: $data['gatewayCode'],
            txLimit: (float) ($data['txLimit'] ?? 0),
            dailyAmountLimit: (float) ($data['dailyAmountLimit'] ?? 0),
            monthlyAmountLimit: (float) ($data['monthlyAmountLimit'] ?? 0),
        );
    }

    public function isClave(): bool
    {
        return in_array($this->gatewayCode, ['CLAVE', 'CROEM_CLAV'], true);
    }
}
