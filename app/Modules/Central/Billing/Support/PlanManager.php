<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Support\Collection;

class PlanManager
{
    public static function all(): Collection
    {
        return Plan::where('is_active', true)->get();
    }

    public static function find(string $id): ?Plan
    {
        if (\Illuminate\Support\Str::isUuid($id)) {
            return Plan::find($id);
        }

        return Plan::where('slug', $id)->first();
    }

    public static function getStripeId(string $id): ?string
    {
        // For now, stripe_id is expected to be part of features or a dedicated mapping
        // In this architecture, we could add a gateway_mappings table or just put it in features
        $plan = self::find($id);
        
        return $plan?->features['stripe_id'] ?? null;
    }
}
