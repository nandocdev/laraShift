<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Infrastructure\Clients;

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
            Log::info('infra.provisioning', ['tenant_id' => $tenant->id, 'slug' => $tenant->slug, 'domain' => $domain, 'provider' => 'railway']);

            $query = <<<'GQL'
            mutation customDomainCreate($input: CustomDomainCreateInput!) {
              customDomainCreate(input: $input) {
                id
                domain
              }
            }
            GQL;

            $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->apiToken])
                ->timeout(5)
                ->retry(3, 200, throw: false)
                ->post('https://backboard.railway.app/graphql/v2', [
                    'query' => $query,
                    'variables' => [
                        'input' => [
                            'projectId' => $this->projectId,
                            'serviceId' => $this->serviceId,
                            'domain' => $domain,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('infra.provisioning.failed', ['tenant_id' => $tenant->id, 'domain' => $domain, 'status' => $response->status(), 'body' => $response->body()]);

                return false;
            }

            $payload = $response->json();
            if (isset($payload['errors'])) {
                Log::warning('infra.provisioning.failed', ['tenant_id' => $tenant->id, 'domain' => $domain, 'errors' => $payload['errors']]);

                return false;
            }

            Log::info('infra.provisioned', ['tenant_id' => $tenant->id, 'domain' => $domain]);

            return true;
        } catch (\Exception $e) {
            Log::error('infra.provisioning.failed', ['tenant_id' => $tenant->id, 'domain' => $domain, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
