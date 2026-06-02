<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Laravel\Cashier\Checkout;

final readonly class CreateCheckoutSessionAction
{
    public function execute(Tenant $tenant, string $planId): Checkout
    {
        $stripeId = PlanManager::getStripeId($planId);

        if (! $stripeId) {
            throw new \InvalidArgumentException("Plan [{$planId}] has no Stripe ID configured.");
        }

        return $tenant->newSubscription('default', $stripeId)
            ->checkout([
                'success_url' => route('central.billing.success', ['tenant' => $tenant->id]),
                'cancel_url' => route('central.billing.cancel', ['tenant' => $tenant->id]),
            ]);
    }
}
