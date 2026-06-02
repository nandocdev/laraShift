<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\TenantProvisioned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateTenantAction
{
    /**
     * Executes the atomic provisioning of a new tenant.
     *
     * [SIDE-EFFECTS]
     * - Creates tenant record and domains.
     * - Triggers events for further provisioning (IAM, etc).
     * - Logs activity in central context.
     */
    public function execute(CreateTenantData $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            // 1. Create the tenant record with manual ID to avoid generation issues
            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'id' => Str::uuid()->toString(),
                'name' => $data->name,
                'email' => $data->email,
                'plan_id' => $data->plan_id,
            ]);

            // 2. Create the domain
            $domain = $data->slug . '.' . config('app.central_domain', 'larashift.test');
            $tenant->domains()->create(['domain' => $domain]);

            // 3. Dispatch Event for cross-module provisioning (Decoupling)
            TenantProvisioned::dispatch($tenant, $data->email, 'Administrator');

            activity('provisioning')
                ->performedOn($tenant)
                ->withProperties(['slug' => $data->slug, 'email' => $data->email])
                ->log('tenant_provisioned');

            return $tenant;
        });
    }
}
