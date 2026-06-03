<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantProvisioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $adminEmail,
        public string $adminName = 'Administrator',
        public ?string $password = null,
    ) {}
}
