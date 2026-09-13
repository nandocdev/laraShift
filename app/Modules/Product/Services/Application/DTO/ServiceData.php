<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\DTO;

use Spatie\LaravelData\Data;

final class ServiceData extends Data
{
    /**
     * @param  array<array{min_hours: int, refund_percent: int}>|null  $cancellationPolicy
     * @param  array<int, string>  $locationIds
     */
    public function __construct(
        public string $name,
        public string $slug,
        public int $durationMinutes,
        public int $capacity = 1,
        public int $priceCents = 0,
        public ?string $categoryId = null,
        public ?string $description = null,
        public int $bufferBeforeMinutes = 0,
        public int $bufferAfterMinutes = 0,
        public ?string $currency = null,
        public string $depositType = 'none',
        public ?int $depositValue = null,
        public ?int $cancellationWindowHours = null,
        public ?array $cancellationPolicy = null,
        public bool $isActive = true,
        public array $locationIds = [],
    ) {}
}
