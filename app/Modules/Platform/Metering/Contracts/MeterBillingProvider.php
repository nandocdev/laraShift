<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Contracts;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Metering\Domain\Meter;

/**
 * Gateway contract for metered billing (usage-based charging).
 *
 * Implementations are bound by integration modules (e.g. DlocalServiceProvider)
 * and only activated when config('metering.provider') matches the integration.
 * The Metering module never depends on a concrete gateway.
 */
interface MeterBillingProvider
{
    /**
     * Reports the aggregated usage of a billable meter for a closed period.
     *
     * @throws \Throwable When the provider rejects the report (job will retry).
     */
    public function reportUsage(TenantContract $tenant, Meter $meter, int $quantity, string $period): void;
}
