<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantApiKeyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $keyId,
        public string $keyName,
        public string $tenantId,
        public array $scopes
    ) {}
}
