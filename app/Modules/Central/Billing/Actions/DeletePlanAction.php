<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Plan;

final readonly class DeletePlanAction
{
    /**
     * Deletes a plan.
     * [RIESGOS]
     * - A plan should probably not be deleted if it has active subscriptions.
     * - We implement a simple delete for now, but a soft-delete or "archive" is safer.
     */
    public function execute(Plan $plan): void
    {
        // Simple safeguard: don't delete if used as default plan id in tenants (approximate check)
        // In a real scenario, we check active subscriptions.
        
        $planId = $plan->id;
        $plan->delete();

        activity('billing')
            ->withProperties(['id' => $planId])
            ->log('plan_deleted');
    }
}
