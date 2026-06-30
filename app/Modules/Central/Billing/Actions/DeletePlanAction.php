<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Plan;

final readonly class DeletePlanAction
{
    /**
     * Retires a plan using SoftDeletes.
     *
     * This allows existing tenants and historical invoices to maintain
     * their references, while preventing the plan from being selected
     * for new subscriptions.
     */
    public function execute(Plan $plan): void
    {
        $planId = $plan->id;
        $planSlug = $plan->slug;

        // Perform soft delete
        $plan->delete();

        activity('billing')
            ->performedOn($plan)
            ->withProperties(['id' => $planId, 'slug' => $planSlug])
            ->log('plan_retired');
    }
}
