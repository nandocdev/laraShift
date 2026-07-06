<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantApiKeyRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $keyId,
        public string $tenantId
    ) {}
}
