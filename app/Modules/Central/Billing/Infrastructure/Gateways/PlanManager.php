<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Catalog\Domain\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlanManager
{
    public static function all(): Collection
    {
        if (app()->runningUnitTests()) {
            return Plan::where('is_active', true)->withoutTrashed()->get();
        }

        return Cache::remember('plans:active', 3600, fn () => Plan::where('is_active', true)->withoutTrashed()->get());
    }

    public static function find(string $id): ?Plan
    {
        if (Str::isUuid($id)) {
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
