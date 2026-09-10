<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Services;

use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Platform\Contracts\TenantContract;

/**
 * Domain entry point for billing. The application never touches a gateway
 * directly; it asks Billing for a tenant scope and calls intent methods.
 *
 * Resolved via the container (autowired). Has no Eloquent, no HTTP, no
 * gateway SDKs — it only orchestrates BillingManager + Actions.
 */
final readonly class Billing
{
    public function __construct(
        private BillingManager $manager,
    ) {}

    public function for(TenantContract $tenant): BillingTenantScope
    {
        return new BillingTenantScope($tenant, $this->manager);
    }
}
