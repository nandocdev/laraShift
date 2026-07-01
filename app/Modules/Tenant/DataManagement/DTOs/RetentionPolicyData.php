<?php

declare(strict_types=1);

namespace App\Modules\Tenant\DataManagement\DTOs;

use Spatie\LaravelData\Data;

final class RetentionPolicyData extends Data
{
    public function __construct(
        public int $audit_logs = 365,
        public int $notifications = 180,
        public int $activity_log = 365,
        public int $exports = 30,
        public int $backups = 7,
    ) {}
}
