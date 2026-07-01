<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\DTOs;

use Spatie\LaravelData\Data;

final class CreateTicketData extends Data
{
    public function __construct(
        public string $tenantId,
        public string $subject,
        public string $description,
        public string $priority = 'medium',
        public ?string $assignedTo = null,
    ) {}
}
