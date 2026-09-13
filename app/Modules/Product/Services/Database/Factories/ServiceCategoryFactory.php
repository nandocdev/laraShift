<?php

declare(strict_types=1);

namespace App\Modules\Product\Services\Database\Factories;

use App\Modules\Product\Services\Domain\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'id' => Str::uuid()->toString(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'color' => '#4f46e5',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
