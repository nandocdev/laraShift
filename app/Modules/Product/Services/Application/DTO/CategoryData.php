<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Application\DTO;

use Spatie\LaravelData\Data;

final class CategoryData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description = null,
        public ?string $color = null,
        public int $sortOrder = 0,
        public bool $isActive = true,
    ) {}
}
