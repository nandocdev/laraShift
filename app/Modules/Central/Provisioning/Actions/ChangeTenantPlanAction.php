<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Services\ProrationCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\SubscriptionUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ChangeTenantPlanAction
{
    public function __construct(
        private ProrationCalculator $proration,
    ) {}

    public function execute(Tenant $tenant, string $newPlanId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($tenant, $newPlanId, $reason) {
            $oldPlan = $tenant->plan;
            $newPlan = Plan::where('slug', $newPlanId)->orWhere('id', $newPlanId)->first();

            if (! $newPlan) {
                throw new \InvalidArgumentException("Plan '{$newPlanId}' not found.");
            }

            if (! $newPlan->is_active) {
                throw new \InvalidArgumentException("Plan '{$newPlan->name}' is not active.");
            }

            $proration = $this->proration->describe($oldPlan, $newPlan);

            $oldPlanId = $tenant->plan_id;
            $tenant->update(['plan_id' => $newPlan->slug ?? $newPlan->id]);

            SubscriptionUpdated::dispatch(
                $tenant->id,
                '',
                $oldPlanId,
                $newPlan->slug ?? $newPlan->id,
            );

            activity('billing')
                ->performedOn($tenant)
                ->withProperties([
                    'old_plan' => $oldPlanId,
                    'new_plan' => $newPlan->slug,
                    'proration' => $proration,
                    'reason' => $reason,
                ])
                ->log('tenant_plan_changed');

            Log::info("Tenant plan changed: {$tenant->slug} {$oldPlanId} → {$newPlan->slug}", [
                'tenant_id' => $tenant->id,
                'proration' => $proration,
            ]);

            return [
                'old_plan' => $oldPlanId,
                'new_plan' => $newPlan->slug,
                'proration' => $proration,
            ];
        });
    }
}
