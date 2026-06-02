<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = config('plans');

        foreach ($plans as $slug => $data) {
            $plan = Plan::where('slug', $slug)->first();
            
            Plan::updateOrCreate(
                ['slug' => $slug],
                [
                    'id' => $plan ? $plan->id : Str::uuid()->toString(),
                    'name' => $data['name'],
                    'price_monthly' => $data['price'],
                    'price_yearly' => (int) (($data['price'] * 12) * 0.8), // 20% discount for yearly
                    'features' => [
                        'stripe_id' => $data['stripe_id'] ?? null,
                        'display_features' => $data['features'],
                        'quotas' => $data['quotas'],
                    ],
                ]
            );
        }
    }
}
