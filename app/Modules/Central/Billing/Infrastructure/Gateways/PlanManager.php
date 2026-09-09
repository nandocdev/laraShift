<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Catalog\Domain\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlanManager
{
    public static function all(): Collection
    {
        // Never cache hydrated Eloquent models here. With the secure
        // `cache.serializable_classes=false` default, unserializing models
        // from cache yields __PHP_Incomplete_Class, so every cache hit
        // 500s with a TypeError (and unit tests never catch it because
        // they bypass the cache). The plans table holds a handful of
        // rows; a direct query is cheap and always fresh.
        return Plan::where('is_active', true)->withoutTrashed()->get();
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
        return self::getProviderRef($id, 'stripe');
    }

    /**
     * Provider reference for a plan (price/product id on the gateway side).
     * Convention: features.gateway_ids = {stripe: price_*, dlocal: PLAN-*, clave: service_id},
     * with fallback to the legacy features.stripe_id (stripe) and provider_plan_id column.
     * No prices table until a plan needs 2+ real prices per gateway.
     */
    public static function getProviderRef(string $id, string $gateway): ?string
    {
        $plan = self::find($id);

        if (! $plan) {
            return null;
        }

        $features = $plan->features ?? [];

        if (is_array($features['gateway_ids'] ?? null) && isset($features['gateway_ids'][$gateway])) {
            return (string) $features['gateway_ids'][$gateway];
        }

        if ($gateway === 'stripe' && isset($features['stripe_id'])) {
            return (string) $features['stripe_id'];
        }

        return $plan->provider_plan_id;
    }
}
