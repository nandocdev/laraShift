<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Central\Provisioning\Actions\MarkTenantBillingActive;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Log;

final readonly class SubscribeTenantAction
{
    public function __construct(
        private PlanManager $plans,
        private MarkTenantBillingActive $activate,
    ) {}

    /**
     * dLocal card subscribe path: card already charged (or MIT-ready),
     * create the local subscription row and activate the tenant.
     * Clave never calls this (FulfillSubscription owns that path).
     */
    public function execute(TenantContract $tenant, string $planSlug, ?string $cardId = null): Subscription
    {
        $plan = $this->plans->find($planSlug);
        $periodEnd = $plan->interval === 'year' ? now()->addYear() : now()->addMonth();

        $subscription = Subscription::updateOrCreate(
            ['tenant_id' => (string) $tenant->getId(), 'gateway' => $tenant->getBillingGateway()],
            [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'next_payment_at' => $periodEnd,
                'failed_attempts' => 0,
                'pm_card_id' => $cardId,
            ]
        );

        $this->activate->execute((string) $tenant->getId(), $plan->slug);

        Log::info('billing.subscribed', [
            'subscription_id' => $subscription->id,
            'plan' => $plan->slug,
        ]);

        return $subscription;
    }
}
