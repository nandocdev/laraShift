<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class CreateTenantAction
{
    /**
     * Executes the atomic provisioning of a new tenant.
     *
     * [SIDE-EFFECTS]
     * - Creates tenant database.
     * - Runs tenant migrations.
     * - Creates first admin user in tenant context.
     * - Logs activity in central context.
     */
    public function execute(CreateTenantData $data): Tenant
    {
        // 1. Create the tenant record
        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'name' => $data->name,
            'email' => $data->email,
            'plan_id' => $data->plan_id,
        ]);

        // 2. Create the domain
        $domain = $data->slug . '.' . config('app.central_domain', 'larashift.test');
        $tenant->domains()->create(['domain' => $domain]);

        // 3. Provision the first admin user inside the tenant context
        $tenant->run(function () use ($data) {
            User::create([
                'name' => 'Administrator',
                'email' => $data->email,
                'password' => Hash::make('password'),
            ]);
        });

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['slug' => $data->slug, 'email' => $data->email])
            ->log('tenant_provisioned');

        return $tenant;
    }
}
