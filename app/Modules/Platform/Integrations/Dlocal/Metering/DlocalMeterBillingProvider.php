<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Metering;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Metering\Contracts\MeterBillingProvider;
use App\Modules\Platform\Metering\Domain\Meter;
use Illuminate\Support\Facades\Log;

/**
 * dLocal metered-billing integration.
 *
 * NOTE: dLocal exposes no native "meter" API (unlike Stripe), so this provider
 * is a DEFERRED-CHARGE integration point: it records the closed-period usage so
 * a subsequent recurring-charge/invoicing flow can turn it into a payment.
 *
 * It is only bound when config('metering.provider') === 'dlocal'. In this first
 * iteration it emits observability + the domain event that listeners consume;
 * no HTTP call is made to dLocal until the recurring-charge flow is wired.
 */
final class DlocalMeterBillingProvider implements MeterBillingProvider
{
    public function reportUsage(TenantContract $tenant, Meter $meter, int $quantity, string $period): void
    {
        Log::info('dLocal metered usage deferred for charging', [
            'tenant_id' => $tenant->getId(),
            'meter' => $meter->key,
            'provider_event_name' => $meter->providerEventName,
            'quantity' => $quantity,
            'period' => $period,
        ]);
    }
}
