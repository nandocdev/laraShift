<?php

declare(strict_types=1);

namespace App\Modules\Central\Landings\DTOs;

use Spatie\LaravelData\Data;

class BlockData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public int $version = 1,
        public string $variant = 'default',
        public int $order = 0,
        public bool $visible = true,
        public array $config = [],
        public array $styles = [],
        public array $meta = [],
    ) {}
}
