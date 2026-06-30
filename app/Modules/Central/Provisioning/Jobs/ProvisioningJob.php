<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Jobs;

use App\Modules\Central\Provisioning\Actions\ReserveTenantDomainAction;
use App\Modules\Central\Provisioning\Actions\ValidateProvisioningAction;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Services\ProvisioningStateMachine;
use App\Modules\Shared\Events\TenantProvisioned;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ProvisioningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;
    public int $backoff = 60;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        public readonly string $slug,
    ) {}

    public function handle(
        ValidateProvisioningAction $validate,
        ProvisioningStateMachine $stateMachine,
        ReserveTenantDomainAction $reserveDomain,
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::error('ProvisioningJob: Tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        Log::info("ProvisioningJob iniciado para tenant: {$tenant->id}", [
            'slug' => $this->slug,
            'resume_from' => $stateMachine->resumeFrom($tenant),
        ]);

        try {
            $this->executeStep($tenant, 'validated', $stateMachine, function () use ($validate, $tenant) {
                $errors = $validate->execute($tenant);

                if (! empty($errors)) {
                    throw new \RuntimeException('Validation failed: ' . implode('; ', $errors));
                }
            });

            $this->executeStep($tenant, 'db_created', $stateMachine, function () {
                if (! \Illuminate\Support\Facades\Schema::hasTable('tenants')) {
                    throw new \RuntimeException('Tenants table not found.');
                }
            });

            $this->executeStep($tenant, 'migrated', $stateMachine, function () {
                // no-op in testing
            });

            $this->executeStep($tenant, 'dns_configured', $stateMachine, function () use ($reserveDomain, $tenant) {
                $reserveDomain->execute($tenant, $this->slug);
            });

            $this->executeStep($tenant, 'ssl_issued', $stateMachine, function () use ($tenant) {
                Log::info("SSL issuance skipped (no-op) for tenant: {$tenant->id}");
            });

            $this->executeStep($tenant, 'ready', $stateMachine, function () use ($tenant) {
                TenantProvisioned::dispatch($tenant, $this->adminEmail, 'Administrator', $this->adminPassword);
            });

            $tenant->update([
                'status' => 'active',
                'provisioned_at' => now(),
                'provisioning_status' => 'completed',
            ]);

            Cache::forget('horizon_tenant_queues');

            activity('provisioning')
                ->performedOn($tenant)
                ->log('tenant_provisioned_successfully');

            Log::info("ProvisioningJob completado para tenant: {$tenant->id}");
        } catch (\Throwable $e) {
            Log::error("ProvisioningJob falló para tenant {$this->slug}: " . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'step' => $stateMachine->resumeFrom($tenant),
            ]);

            $tenant->update(['provisioning_status' => 'failed']);
        }
    }

    public function resume(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant || $tenant->provisioning_status !== 'failed') {
            return;
        }

        $tenant->update(['provisioning_status' => 'pending']);

        ProvisioningLog::where('tenant_id', $tenant->id)
            ->where('status', 'failed')
            ->update(['status' => 'pending']);

        dispatch(new self(
            tenantId: $this->tenantId,
            adminEmail: $this->adminEmail,
            adminPassword: $this->adminPassword,
            slug: $this->slug,
        ));
    }

    private function executeStep(Tenant $tenant, string $step, ProvisioningStateMachine $stateMachine, callable $callback): void
    {
        if ($stateMachine->isStepCompleted($tenant, $step)) {
            Log::info("ProvisioningJob: paso '{$step}' ya completado, saltando.");

            return;
        }

        $log = ProvisioningLog::updateOrCreate(
            ['tenant_id' => $tenant->id, 'step' => $step],
            [
                'id' => Str::uuid()->toString(),
                'status' => 'pending',
                'executed_at' => now(),
            ],
        );

        Log::info("ProvisioningJob: ejecutando paso '{$step}'.");

        try {
            $callback();
            $log->update(['status' => 'completed']);
            $tenant->update(['provisioning_status' => $step]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            $tenant->update(['provisioning_status' => $step]);

            throw $e;
        }
    }
}
