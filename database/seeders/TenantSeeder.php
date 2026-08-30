<?php

namespace Database\Seeders;

use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\Actions\ProvisionTenantPipeline;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(
        CreateTenantAction $action,
        ProvisionTenantPipeline $pipeline,
        EnsureTenantRolesExist $ensureRoles
    ): void {
        $testTenants = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme',
                'email' => 'alone-setup-crock@duck.com',
                'plan_id' => 'enterprise',
            ],
            [
                'name' => 'Globex Corp',
                'slug' => 'globex',
                'email' => 'ample-smell-unripe@duck.com',
                'plan_id' => 'pro',
            ],
            [
                'name' => 'Initech',
                'slug' => 'initech',
                'email' => 'gusto-spied-disk@duck.com',
                'plan_id' => 'free',
            ],
        ];

        foreach ($testTenants as $data) {
            $tenant = Tenant::where('slug', $data['slug'])->first();

            if (! $tenant) {
                $tenant = $action->execute(new CreateTenantData(
                    name: $data['name'],
                    slug: $data['slug'],
                    email: $data['email'],
                    plan_id: $data['plan_id'],
                    password: 'password',
                    status: 'active',
                ));
            }

            // Si el tenant está en proceso de provisioning, ejecutar pipeline síncrono
            if (in_array($tenant->status, ['provisioning', 'failed', 'expired'], true)) {
                $pipeline->execute(
                    tenantId: $tenant->id,
                    adminEmail: $data['email'],
                    password: 'password',
                    adminName: 'Administrator',
                    finalStatus: 'active',
                );
            }

            // Garantizar roles del sistema y rol admin en el usuario owner
            $tenant->run(function () use ($tenant, $data, $ensureRoles) {
                $ensureRoles->execute($tenant);

                $user = User::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'email' => $data['email'],
                    ],
                    [
                        'name' => 'Administrator',
                        'password' => Hash::make('password'),
                    ]
                );

                setPermissionsTeamId($tenant->id);
                if (! $user->hasRole('admin')) {
                    $user->assignRole('admin');
                }
            });

            $this->command->info("Tenant provisionado y configurado: {$data['name']} ({$data['slug']}) -> admin: {$data['email']}");
        }
    }
}
