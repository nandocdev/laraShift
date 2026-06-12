<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\DTOs\PlanData;
use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpsertPlanAction
{
    /**
     * Creates or updates a subscription plan.
     */
    public function execute(PlanData $data, ?Plan $plan = null): Plan
    {
        return DB::transaction(function () use ($data, $plan) {
            $attributes = [
                'name' => $data->name,
                'slug' => $data->slug,
                'price_monthly' => $data->price_monthly,
                'price_yearly' => $data->price_yearly,
                'is_active' => $data->is_active,
                'features' => $data->features,
            ];

            if ($plan) {
                $plan->update($attributes);
            } else {
                $attributes['id'] = Str::uuid()->toString();
                $plan = Plan::create($attributes);
            }

            activity('billing')
                ->performedOn($plan)
                ->withProperties($attributes)
                ->log($plan->wasRecentlyCreated ? 'plan_created' : 'plan_updated');

            return $plan;
        });
    }
}
