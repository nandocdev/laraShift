<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Actions;

use App\Modules\Central\Infrastructure\Services\RailwayService;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Log;

final readonly class ProvisionInfrastructureAction
{
    public function __construct(
        private RailwayService $railway,
    ) {}

    /**
     * Handles the external infrastructure provisioning for a new tenant.
     * This includes DNS, CDNs, and Load Balancer rules.
     */
    public function execute(Tenant $tenant): void
    {
        Log::info("Starting infrastructure provisioning for tenant: {$tenant->slug}");

        $primaryDomain = $tenant->domains()->first()?->domain;

        if ($primaryDomain) {
            $this->railway->provisionDomain($tenant, $primaryDomain);
        }

        // Add other infrastructure steps here (e.g. Cloudflare, AWS, etc.)

        activity('infrastructure')
            ->performedOn($tenant)
            ->log('infrastructure_provisioned');
    }
}
