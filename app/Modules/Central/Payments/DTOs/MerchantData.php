<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;

final class MerchantData extends Data {
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $legalName,
        public readonly float $dailyAmountLimit,
        public readonly float $monthlyAmountLimit,
        /** @var ServiceData[] */
        public readonly array $services = [],
    ) {
    }

    public static function fromApiResponse(array $merchant, array $services): self {
        return new self(
            id: $merchant['merchant_idMerchant'],
            slug: $merchant['merchant_slug'],
            name: $merchant['merchant_merchantName'],
            legalName: $merchant['merchant_legalName'] ?? '',
            dailyAmountLimit: (float) ($merchant['merchant_dailyAmountLimit'] ?? 0),
            monthlyAmountLimit: (float) ($merchant['merchant_monthlyAmountLimit'] ?? 0),
            services: array_map(
                fn(array $s) => ServiceData::fromArray($s),
                $services,
            ),
        );
    }
}
