<?php

namespace Database\Seeders;

use App\Modules\Central\Catalog\Domain\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'USD',
            'interval' => 'month',
            'features' => [
                'display_features' => ['basic_dashboard', 'community_support'],
                'gateway_ids' => [],
                'quotas' => [],
            ],
            'is_active' => true,
        ]);

        Plan::firstOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'price_monthly' => 2900,
            'price_yearly' => 29000,
            'currency' => 'USD',
            'interval' => 'month',
            'features' => [
                'display_features' => ['basic_dashboard', 'priority_support', 'api_access'],
                'gateway_ids' => [],
                'quotas' => [],
            ],
            'is_active' => true,
        ]);
    }
}
