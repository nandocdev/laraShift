<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class DeletePlanAction
{
    /**
     * Deletes a plan.
     * Prevents deletion if the plan is currently assigned to any tenant.
     */
    public function execute(Plan $plan): void
    {
        // Protect against deleting a plan in use by a tenant (checking slug, since tenants.plan_id is currently the slug)
        // Or UUID, depending on normalization. The current schema defaults to 'free', so we check slug.
        $inUse = Tenant::where('plan_id', $plan->slug)->orWhere('plan_id', $plan->id)->exists();

        if ($inUse) {
            throw new \Exception(__('Cannot delete a plan that is currently assigned to one or more tenants.'));
        }
        
        $planId = $plan->id;
        $plan->delete();

        activity('billing')
            ->withProperties(['id' => $planId])
            ->log('plan_deleted');
    }
}
