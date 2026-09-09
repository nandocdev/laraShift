<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Services;

use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\HasBillingCapabilities;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use App\Modules\Platform\Contracts\TenantContract;

/**
 * Tenant-scoped billing intents. Subscription lifecycle goes through the
 * existing BillingProvider drivers; availability questions go through
 * BillingCapability::supports() — never `if ($provider === 'stripe')`.
 *
 * subscribe()/changePlan() are intentionally absent: only Stripe has a
 * subscription adapter today and dLocal enrollments are not wired yet.
 * They land here when a second provider implements SubscriptionProvider.
 */
final readonly class BillingTenantScope
{
    public function __construct(
        private TenantContract $tenant,
        private BillingManager $manager,
    ) {}

    public function supports(BillingCapability $capability): bool
    {
        $gateway = $this->manager->paymentGatewayForTenant($this->tenant);

        return $gateway instanceof HasBillingCapabilities
            && $gateway->supports($capability);
    }

    public function checkoutUrl(PlanRef $plan): string
    {
        return $this->manager->createCheckoutSession($this->tenant, $plan->planId);
    }

    public function cancel(string $subscriptionId, bool $immediately = false): void
    {
        $this->manager->cancelSubscription($this->tenant, $subscriptionId, $immediately);
    }

    public function sync(): void
    {
        $this->manager->syncSubscription($this->tenant);
    }

    public function active(): bool
    {
        return Subscription::where('tenant_id', (string) $this->tenant->getId())
            ->where('status', 'active')
            ->exists();
    }
}
