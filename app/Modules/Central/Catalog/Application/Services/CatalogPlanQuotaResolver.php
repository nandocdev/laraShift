<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Services;

use App\Modules\Platform\Contracts\PlanQuotaResolver;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class CatalogPlanQuotaResolver implements PlanQuotaResolver
{
    public function __construct(private PlanManager $plans) {}

    public function limitFor(TenantContract $tenant, string $metric): ?int
    {
        try {
            $quotas = $this->plans->quotas($this->plans->find($tenant->getPlanSlug()));
        } catch (ModelNotFoundException) {
            return null;
        }

        if (array_key_exists($metric, $quotas) && is_numeric($quotas[$metric])) {
            return (int) $quotas[$metric];
        }

        return null;
    }
}
