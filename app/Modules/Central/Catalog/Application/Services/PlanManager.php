<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Services;

use App\Modules\Central\Catalog\Domain\Models\Plan;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class PlanManager
{
    public function find(string $slug): Plan
    {
        $plan = Plan::where('slug', $slug)->where('is_active', true)->first();

        if (! $plan) {
            throw (new ModelNotFoundException)->setModel(Plan::class, $slug);
        }

        return $plan;
    }

    /**
     * Resolve the gateway-specific plan reference. Reads
     * features.gateway_ids[$gateway] with fallback to provider_plan_id.
     * Never caches hydrated models.
     */
    public function getProviderRef(Plan $plan, string $gateway): ?string
    {
        return $plan->gatewayIds()[$gateway] ?? $plan->provider_plan_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function quotas(Plan $plan): array
    {
        $quotas = $plan->features['quotas'] ?? [];

        return is_array($quotas) ? $quotas : [];
    }
}
