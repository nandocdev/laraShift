<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use Illuminate\Support\Collection;

class PlanManager
{
    public static function all(): Collection
    {
        return collect(config('plans'));
    }

    public static function find(string $id): ?array
    {
        return config("plans.{$id}");
    }

    public static function getStripeId(string $id): ?string
    {
        return config("plans.{$id}.stripe_id");
    }
}
