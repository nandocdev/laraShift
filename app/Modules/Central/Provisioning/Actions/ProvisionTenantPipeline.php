<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Operations\Application\Actions\ProvisionInfrastructureAction;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\TenantProvisioned;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Executes the resumable provisioning pipeline for a tenant.
 *
 * Each step is recorded in provisioning_logs keyed by (tenant_id, step).
 * On retry, completed steps are skipped and only pending/failed ones run,
 * so a failure never restarts the whole provisioning from scratch.
 *
 * Steps: db_schema (core data) -> infrastructure -> admin_user (via
 * TenantProvisioned event) -> finalize status.
 */
final readonly class ProvisionTenantPipeline
{
    public function __construct(
        private SetupTenantCoreDataAction $setupCoreData,
        private ProvisionInfrastructureAction $provisionInfra,
    ) {}

    public function execute(
        string $tenantId,
        string $adminEmail,
        ?string $password,
        string $adminName = 'Administrator',
        string $finalStatus = 'active',
    ): void {
        $tenant = Tenant::findOrFail($tenantId);

        if (! in_array($tenant->status, ['provisioning', 'failed', 'expired'], true)) {
            throw new \RuntimeException(
                "Cannot provision tenant [{$tenant->slug}]: current status is [{$tenant->status}]."
            );
        }

        $this->runStep($tenant, 'db_schema', fn () => $this->setupCoreData->execute($tenant));

        $this->runStep($tenant, 'infrastructure', fn () => $this->provisionInfra->execute($tenant));

        $this->runStep($tenant, 'admin_user', function () use ($tenant, $adminEmail, $adminName, $password) {
            TenantProvisioned::dispatch($tenant, $adminEmail, $adminName, $password);
        });

        $tenant->update([
            'status' => $finalStatus,
            'provisioned_at' => now(),
        ]);

        Cache::forget('horizon_tenant_queues');

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['status' => $finalStatus])
            ->log('tenant_provisioning_completed');
    }

    private function runStep(Tenant $tenant, string $step, callable $callback): void
    {
        $log = ProvisioningLog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'step' => $step],
            [
                'id' => Str::uuid()->toString(),
                'status' => 'pending',
                'executed_at' => now(),
                'error' => null,
            ]
        );

        if ($log->status === 'completed') {
            return;
        }

        $log->update(['status' => 'pending', 'executed_at' => now(), 'error' => null]);

        try {
            $callback();
            $log->update(['status' => 'completed']);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
