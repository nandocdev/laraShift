<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Application\DTO;

use Spatie\LaravelData\Data;

final class ResourceData extends Data
{
    /**
     * @param  array<int, string>|null  $skills
     * @param  array<int, string>  $serviceIds
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $type = 'staff',
        public ?string $locationId = null,
        public ?string $description = null,
        public int $capacity = 1,
        public ?array $skills = null,
        public ?int $rateCents = null,
        public ?string $currency = null,
        public bool $isActive = true,
        public array $serviceIds = [],
    ) {}
}
