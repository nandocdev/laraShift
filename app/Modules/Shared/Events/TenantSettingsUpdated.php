<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantSettingsUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $changedFields
    ) {}
}
