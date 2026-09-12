<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Infrastructure\Console;

use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Settings\Infrastructure\Services\PlatformPolicies;
use Illuminate\Console\Command;

/**
 * Reconciles the tenant lifecycle:
 *  - re-dispatches provisioning for failed or stale 'provisioning' tenants
 *  - expires 'pending_payment' tenants older than 24h (billing, §19)
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
        $this->expireUnpaidTenants();

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
            ->where('created_at', '<', now()->subMinutes(PlatformPolicies::staleProvisioningMinutes()))
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

    private function expireUnpaidTenants(): void
    {
        Tenant::where('status', 'pending_payment')
            ->whereNull('deleted_at')
            ->where('created_at', '<', now()->subHours(24))
            ->chunkById(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    $tenant->update(['status' => 'expired']);

                    activity('billing')
                        ->performedOn($tenant)
                        ->log('tenant_pending_payment_expired');
                }
            });
    }
}
