<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Central\Features\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['key' => 'crm.contacts', 'name' => 'Contact Management', 'module' => 'CRM'],
            ['key' => 'crm.pipeline', 'name' => 'Sales Pipeline', 'module' => 'CRM'],
            ['key' => 'reports.basic', 'name' => 'Basic Reports', 'module' => 'Reports'],
            ['key' => 'reports.advanced', 'name' => 'Advanced Analytics', 'module' => 'Reports'],
            ['key' => 'branding.custom_domain', 'name' => 'Custom Domains', 'module' => 'Branding'],
            ['key' => 'api.access', 'name' => 'API Access', 'module' => 'API'],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(
                ['key' => $f['key']],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $f['name'],
                    'module' => $f['module'],
                    'is_active' => true,
                ]
            );
        }
    }
}
