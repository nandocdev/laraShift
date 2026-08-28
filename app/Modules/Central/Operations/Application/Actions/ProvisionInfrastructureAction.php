<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Application\Actions;

use App\Modules\Central\Operations\Infrastructure\Clients\RailwayService;
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
        Log::info('infra.provisioning', ['tenant_id' => $tenant->id, 'slug' => $tenant->slug]);

        $primaryDomain = $tenant->domains()->first()?->domain;

        if ($primaryDomain) {
            $ok = $this->railway->provisionDomain($tenant, $primaryDomain);

            if ($ok === false) {
                throw new \RuntimeException("Railway provisioning failed for domain {$primaryDomain}");
            }
        }

        // Add other infrastructure steps here (e.g. Cloudflare, AWS, etc.)

        activity('infrastructure')
            ->performedOn($tenant)
            ->log('infrastructure_provisioned');
    }
}
