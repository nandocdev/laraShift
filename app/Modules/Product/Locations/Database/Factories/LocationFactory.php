<?php

declare(strict_types=1);

namespace App\Modules\Product\Locations\Database\Factories;

use App\Modules\Product\Locations\Domain\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->randomNumber(5),
            'address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'country' => 'PA',
            ],
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'timezone' => null,
            'settings' => [],
            'is_active' => true,
        ];
    }
}
