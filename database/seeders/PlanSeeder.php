<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder {
   public function run(): void {
      if (!\Illuminate\Support\Facades\Schema::hasColumn('plans', 'slug')) {
         echo "plans table does not have 'slug' column; skipping PlanSeeder.\n";
         return;
      }
      $plans = [
         [
            'name' => 'Free',
            'slug' => 'free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'is_active' => true,
            'features' => [
               'stripe_id' => null,
               'display_features' => ['Basic CRM', '1 Branch', 'Up to 3 staff'],
               'quotas' => ['branches' => 1, 'staff' => 3, 'bookings' => 100, 'invitations' => 5, 'api_keys' => 2],
            ],
         ],
         [
            'name' => 'Pro',
            'slug' => 'pro',
            'price_monthly' => 2999,
            'price_yearly' => 29900,
            'is_active' => true,
            'features' => [
               'stripe_id' => 'price_pro_monthly',
               'display_features' => ['CRM Pipeline', 'API Access', 'Custom Domain'],
               'quotas' => ['branches' => 5, 'staff' => 20, 'bookings' => 1000, 'invitations' => 20, 'api_keys' => 10],
            ],
         ],
         [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price_monthly' => 9999,
            'price_yearly' => 99900,
            'is_active' => true,
            'features' => [
               'stripe_id' => 'price_enterprise_monthly',
               'display_features' => ['Advanced Analytics', 'SLA', 'Priority Support'],
               'quotas' => ['branches' => 50, 'staff' => 500, 'bookings' => 100000, 'invitations' => -1, 'api_keys' => 100],
            ],
         ],
      ];

      foreach ($plans as $p) {
         $plan = Plan::updateOrCreate(
            ['slug' => $p['slug']],
            [
               'name' => $p['name'],
               'price_monthly' => $p['price_monthly'],
               'price_yearly' => $p['price_yearly'],
               'amount' => $p['price_monthly'] / 100,
               'currency' => 'USD',
               'interval' => 'month',
               'interval_count' => 1,
               'provider_plan_id' => $p['slug'] === 'free' ? null : "PF_".strtoupper($p['slug']),
               'is_active' => $p['is_active'],
               'features' => $p['features'],
            ]
         );

         // Attach catalog features where applicable
         $featureKeys = [];
         if (in_array($p['slug'], ['pro'])) {
            $featureKeys = ['crm.pipeline', 'api.access', 'branding.custom_domain'];
         }

         if (in_array($p['slug'], ['enterprise'])) {
            $featureKeys = ['reports.advanced', 'api.access', 'branding.custom_domain'];
         }

         if (! empty($featureKeys)) {
            $featureIds = Feature::whereIn('key', $featureKeys)->pluck('id')->toArray();
            $plan->catalogFeatures()->sync($featureIds);
         }
      }
   }
}
