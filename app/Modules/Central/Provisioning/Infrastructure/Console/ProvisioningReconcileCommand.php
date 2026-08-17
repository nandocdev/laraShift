<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Infrastructure\Console;

use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Notifications\OnboardingExpiredNotification;
use Illuminate\Console\Command;

/**
 * Reconciles the tenant lifecycle:
 *  - re-dispatches provisioning for failed or stale 'provisioning' tenants
 *  - expires 'pending_payment' tenants that never paid within the window
 */
class ProvisioningReconcileCommand extends Command
{
    protected $signature = 'provisioning:reconcile {--tenant= : Only reconcile a specific tenant ID}';

    protected $description = 'Recover stuck provisioning and expire unpaid pending_payment tenants';

    public function handle(): int
    {
        if ($tenantId = $this->option('tenant')) {
            $tenant = Tenant::find((string) $tenantId);

            if (! $tenant) {
                $this->error('Tenant not found.');

                return self::FAILURE;
            }

            $tenant->status === 'pending_payment'
                ? $this->expireTenant($tenant)
                : $this->retryTenant($tenant);

            return self::SUCCESS;
        }

        $this->retryFailedProvisioning();
        $this->retryStaleProvisioning();
        $this->expireUnpaidTenants();

        return self::SUCCESS;
    }

    private function retryFailedProvisioning(): void
    {
        $failed = Tenant::where('status', 'failed')->whereNull('deleted_at')->get();

        foreach ($failed as $tenant) {
            $this->retryTenant($tenant);
        }
    }

    private function retryStaleProvisioning(): void
    {
        $stale = Tenant::where('status', 'provisioning')
            ->whereNull('deleted_at')
            ->where('created_at', '<', now()->subMinutes((int) config('provisioning.stale_provisioning_minutes')))
            ->get();

        foreach ($stale as $tenant) {
            $this->retryTenant($tenant);
        }
    }

    private function expireUnpaidTenants(): void
    {
        $expired = Tenant::where('status', 'pending_payment')
            ->whereNull('deleted_at')
            ->where('created_at', '<', now()->subHours((int) config('provisioning.pending_payment_expiry_hours')))
            ->get();

        foreach ($expired as $tenant) {
            $this->expireTenant($tenant);
        }
    }

    private function expireTenant(Tenant $tenant): void
    {
        $tenant->update(['status' => 'expired', 'provisioned_at' => null]);

        $tenant->notify(new OnboardingExpiredNotification(
            tenantName: $tenant->name,
            domain: $tenant->getDomain() ?: ($tenant->slug.'.'.config('tenancy.central_domain')),
        ));

        activity('provisioning')
            ->performedOn($tenant)
            ->log('tenant_onboarding_expired');
    }

    private function retryTenant(Tenant $tenant): void
    {
        $finalStatus = $this->resolveFinalStatus($tenant);

        ProvisionTenantJob::dispatch(
            tenantId: $tenant->id,
            adminEmail: $tenant->email,
            password: null,
            adminName: 'Administrator',
            finalStatus: $finalStatus,
        );

        $tenant->update(['status' => 'provisioning']);

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['final_status' => $finalStatus])
            ->log('tenant_provisioning_requeued');
    }

    private function resolveFinalStatus(Tenant $tenant): string
    {
        $plan = $tenant->plan;

        return $plan && $plan->price_monthly->isPositive() ? 'pending_payment' : 'active';
    }
}
