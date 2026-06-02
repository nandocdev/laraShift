<?php

namespace Database\Seeders;

use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateTenantAction $action): void
    {
        $testTenants = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme',
                'email' => 'admin@acme.test',
                'plan_id' => 'enterprise',
            ],
            [
                'name' => 'Globex Corp',
                'slug' => 'globex',
                'email' => 'admin@globex.test',
                'plan_id' => 'pro',
            ],
            [
                'name' => 'Initech',
                'slug' => 'initech',
                'email' => 'admin@initech.test',
                'plan_id' => 'free',
            ],
        ];

        foreach ($testTenants as $data) {
            // Skip if already exists to avoid unique constraint errors
            if (\App\Modules\Central\Provisioning\Models\Tenant::where('email', $data['email'])->exists()) {
                continue;
            }

            $action->execute(new CreateTenantData(
                name: $data['name'],
                slug: $data['slug'],
                email: $data['email'],
                plan_id: $data['plan_id']
            ));

            $this->command->info("Tenant provisionado: {$data['name']} ({$data['slug']})");
        }
    }
}
