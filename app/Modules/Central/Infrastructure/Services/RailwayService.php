<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RailwayService
{
    private ?string $apiToken;

    private ?string $projectId;

    private ?string $serviceId;

    public function __construct()
    {
        $this->apiToken = config('infrastructure.railway.api_token');
        $this->projectId = config('infrastructure.railway.project_id');
        $this->serviceId = config('infrastructure.railway.service_id');
    }

    /**
     * Provisions a custom domain in Railway for the tenant.
     *
     * [RIESGOS]
     * - API Rate limits.
     * - DNS propagation delays.
     */
    public function provisionDomain(Tenant $tenant, string $domain): bool
    {
        if (! $this->apiToken || ! $this->projectId || ! $this->serviceId) {
            Log::info('Railway infrastructure skipped: Missing configuration.');

            return true; // No-op for local/unconfigured envs
        }

        try {
            // Railway uses a GraphQL API
            // This is a placeholder for the actual domain addition mutation
            Log::info("Provisioning Railway domain for tenant {$tenant->slug}: {$domain}");

            /*
            $query = <<<'GQL'
            mutation customDomainCreate($input: CustomDomainCreateInput!) {
              customDomainCreate(input: $input) {
                id
                domain
              }
            }
            GQL;

            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->apiToken])
                ->post('https://backboard.railway.app/graphql/v2', [
                    'query' => $query,
                    'variables' => [
                        'input' => [
                            'projectId' => $this->projectId,
                            'serviceId' => $this->serviceId,
                            'domain' => $domain,
                        ]
                    ]
                ]);
            */

            return true;
        } catch (\Exception $e) {
            Log::error('Railway domain provisioning failed: '.$e->getMessage());

            return false;
        }
    }
}
