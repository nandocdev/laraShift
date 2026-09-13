<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Application\DTO;

use Spatie\LaravelData\Data;

final class LocationData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $timezone = null,
        public bool $isActive = true,
        /** @var array{line1?: ?string, city?: ?string, state?: ?string, postal_code?: ?string, country?: ?string}|null */
        public ?array $address = null,
    ) {}
}
