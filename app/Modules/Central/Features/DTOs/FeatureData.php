<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\DTOs;

use Spatie\LaravelData\Data;

final class FeatureData extends Data
{
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description = null,
        public ?string $module = null,
        public bool $is_active = true,
        public array $targeting = [],
    ) {}
}
