<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\DTOs;

use Spatie\LaravelData\Data;

final class SupportAuditEntryData extends Data
{
    public function __construct(
        public string $id,
        public string $action,
        public string $severity,
        public ?string $userName,
        public ?string $resource,
        public ?string $resourceId,
        public string $occurredAt,
    ) {}
}
