<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantProvisioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TenantContract $tenant,
        public string $adminEmail,
        public string $adminName = 'Administrator',
        public ?string $password = null,
    ) {}
}
