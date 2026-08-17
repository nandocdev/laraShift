<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Database\Factories;

use App\Modules\Platform\Metering\Domain\Enums\AggregationType;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageRollup>
 */
class UsageRollupFactory extends Factory
{
    protected $model = UsageRollup::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => null,
            'meter' => 'bookings',
            'period' => now()->format('Y-m'),
            'value' => 0,
            'aggregation' => AggregationType::Sum->value,
            'billed_at' => null,
        ];
    }
}
