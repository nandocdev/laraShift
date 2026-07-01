<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\DTOs;

use Spatie\LaravelData\Data;

final class ImportData extends Data
{
    public function __construct(
        public string $type,
        public array $records,
        public bool $overwrite = false,
    ) {}
}
