<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\DTOs;

use Spatie\LaravelData\Data;

final class UpdateTicketData extends Data
{
    public function __construct(
        public ?string $status = null,
        public ?string $priority = null,
        public ?string $assignedTo = null,
    ) {}
}
