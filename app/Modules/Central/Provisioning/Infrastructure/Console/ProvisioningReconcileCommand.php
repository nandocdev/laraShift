<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Infrastructure\Console;

use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Reconciles the tenant lifecycle:
 *  - re-dispatches provisioning for failed or stale 'provisioning' tenants
 *  - expires tenants stuck in non-terminal states is handled via status flow
 */
class ProvisioningReconcileCommand extends Command
{
    protected $signature = 'provisioning:reconcile {--tenant= : Only reconcile a specific tenant ID}';

    protected $description = 'Recover stuck provisioning';

    public function handle(): int
    {
        if ($tenantId = $this->option('tenant')) {
            $tenant = Tenant::find((string) $tenantId);

            if (! $tenant) {
                $this->error('Tenant not found.');

                return self::FAILURE;
            }

            $this->retryTenant($tenant);

            return self::SUCCESS;
        }

        $this->retryFailedProvisioning();
        $this->retryStaleProvisioning();

        return self::SUCCESS;
    }

    private function retryFailedProvisioning(): void
    {
        Tenant::where('status', 'failed')->whereNull('deleted_at')->chunkById(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                $this->retryTenant($tenant);
            }
        });
    }

    private function retryStaleProvisioning(): void
    {
        Tenant::where('status', 'provisioning')
            ->whereNull('deleted_at')
            ->where('created_at', '<', now()->subMinutes((int) config('provisioning.stale_provisioning_minutes')))
            ->chunkById(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    $this->retryTenant($tenant);
                }
            });
    }

    private function retryTenant(Tenant $tenant): void
    {
        ProvisionTenantJob::dispatch(
            tenantId: $tenant->id,
            adminEmail: $tenant->email,
            password: null,
            adminName: 'Administrator',
            finalStatus: 'active',
        );

        $tenant->update(['status' => 'provisioning']);

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['final_status' => 'active'])
            ->log('tenant_provisioning_requeued');
    }
}
