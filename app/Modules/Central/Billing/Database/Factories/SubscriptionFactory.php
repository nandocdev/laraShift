<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Database\Factories;

use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'status' => SubscriptionStatus::Active,
            'gateway' => 'clave',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'failed_attempts' => 0,
            'cancel_at_period_end' => false,
        ];
    }
}
