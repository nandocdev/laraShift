<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Database\Factories;

use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => null,
            'meter' => 'bookings',
            'quantity' => 1,
            'occurred_at' => now(),
            'metadata' => [],
            'dedupe_key' => null,
            'subscription_item_id' => null,
        ];
    }
}
