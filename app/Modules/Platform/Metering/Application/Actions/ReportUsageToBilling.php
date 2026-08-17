<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\Actions;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Metering\Contracts\MeterBillingProvider;
use App\Modules\Platform\Metering\Domain\Events\MeterUsageBillingReported;
use App\Modules\Platform\Metering\Domain\Meter;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Reports a closed period's aggregated usage to the active billing provider.
 * Idempotent per rollup: once billed_at is set, the period is never re-reported.
 */
final readonly class ReportUsageToBilling
{
    public function execute(TenantContract $tenant, Meter $meter, int $value, string $period, UsageRollup $rollup): void
    {
        if (! $meter->billable) {
            return;
        }

        if ($rollup->isBilled()) {
            return;
        }

        if (config('metering.provider') === null || ! app()->bound(MeterBillingProvider::class)) {
            Log::warning('Billable meter without an active MeterBillingProvider', [
                'tenant_id' => $tenant->getId(),
                'meter' => $meter->key,
                'period' => $period,
            ]);

            return;
        }

        $provider = app(MeterBillingProvider::class);

        $provider->reportUsage($tenant, $meter, $value, $period);

        $rollup->update(['billed_at' => now()]);

        Event::dispatch(new MeterUsageBillingReported(
            meter: $meter->key,
            quantity: $value,
            period: $period,
            tenantId: (string) $tenant->getId(),
        ));
    }
}
