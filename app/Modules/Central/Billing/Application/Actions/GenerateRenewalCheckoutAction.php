<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Infrastructure\Notifications\RenewalCheckoutLinkNotification;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Platform\Contracts\Billing\CheckoutSessionData;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GenerateRenewalCheckoutAction
{
    public function __construct(
        private CreateCheckoutSessionAction $checkouts,
        private PlanManager $plans,
    ) {}

    /**
     * Clave renewal = a fresh checkout linked to the living subscription.
     * Called by billing:process-recurring when current_period_end is near.
     */
    public function execute(Subscription $subscription): CheckoutSessionData
    {
        $planSlug = $subscription->plan_id
            ? Plan::where('id', $subscription->plan_id)->value('slug')
            : null;

        if (! is_string($planSlug) || $planSlug === '') {
            throw new RuntimeException('Subscription has no plan.');
        }

        $plan = $this->plans->find($planSlug);
        $tenant = $this->resolveTenant((string) $subscription->tenant_id);

        $periodTag = $subscription->current_period_end?->format('Ym') ?? now()->format('Ym');
        $displayId = "rnw_{$subscription->id}_{$periodTag}";

        $session = $this->checkouts->execute($tenant, new PlanRef(
            slug: $plan->slug,
            amountCents: $plan->price_monthly,
            currency: $plan->currency,
            gatewayIds: $plan->gatewayIds(),
        ), $displayId);

        $subscription->update([
            'renewal_link_sent_at' => now(),
            'renewal_link_expires_at' => $subscription->current_period_end->copy()->addDays(3),
        ]);

        $tenant->notify(new RenewalCheckoutLinkNotification(
            $session->url,
            $subscription->renewal_link_expires_at,
        ));

        Log::info('billing.renewal_link_generated', [
            'subscription_id' => $subscription->id,
            'display_id' => $displayId,
        ]);

        return $session;
    }

    private function resolveTenant(string $tenantId): TenantContract
    {
        $model = config('tenancy.tenant_model');

        return $model::findOrFail($tenantId);
    }
}
