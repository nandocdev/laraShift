<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Database\Factories;

use App\Modules\Product\Services\Domain\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'id' => Str::uuid()->toString(),
            'category_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(5),
            'description' => $this->faker->sentence(),
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90]),
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 10,
            'capacity' => 1,
            'price_cents' => 2500,
            'currency' => null,
            'deposit_type' => 'none',
            'deposit_value' => null,
            'cancellation_window_hours' => 24,
            'cancellation_policy' => null,
            'is_active' => true,
        ];
    }
}
