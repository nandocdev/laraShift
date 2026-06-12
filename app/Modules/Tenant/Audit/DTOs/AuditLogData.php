<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\DTOs;

use App\Modules\Tenant\Audit\Enums\AuditAction;
use Spatie\LaravelData\Data;

final class AuditLogData extends Data
{
    public function __construct(
        public AuditAction|string $action,
        public ?string $resource = null,
        public ?string $resourceId = null,
        public ?array $metadata = null,
        public ?string $ip = null,
        public ?string $userId = null,
    ) {}
}
