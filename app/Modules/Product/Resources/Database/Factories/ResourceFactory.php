<?php

declare(strict_types=1);

namespace App\Modules\Product\Resources\Database\Factories;

use App\Modules\Product\Resources\Domain\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'id' => Str::uuid()->toString(),
            'location_id' => null,
            'type' => 'staff',
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(5),
            'description' => $this->faker->sentence(),
            'capacity' => 1,
            'skills' => ['haircut', 'beard'],
            'rate_cents' => null,
            'currency' => null,
            'is_active' => true,
        ];
    }
}
