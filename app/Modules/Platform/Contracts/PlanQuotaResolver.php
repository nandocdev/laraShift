<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

/**
 * Resolves the plan-defined limit for a quota metric.
 * Returns null when the tenant's plan sets no limit: callers fall back
 * to the tenant default (usually unlimited).
 */
interface PlanQuotaResolver
{
    public function limitFor(TenantContract $tenant, string $metric): ?int;
}
