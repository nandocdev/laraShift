<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Jobs;

use App\Modules\Central\Infrastructure\Actions\ProvisionInfrastructureAction;
use App\Modules\Central\Provisioning\Actions\ReserveTenantDomainAction;
use App\Modules\Central\Provisioning\Actions\SetupTenantCoreDataAction;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\TenantProvisioned;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @deprecated Use ProvisioningJob instead (more robust step-based approach).
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * Desacoplamiento de la provisión de infraestructura del flujo síncrono de pago.
 * Garantiza que la pasarela no reciba Timeouts mientras se aprovisiona DNS y BD.
 *
 * Legacy: stores full Tenant model in queue payload.
 * Replace with ProvisioningJob which uses tenantId string and
 * has explicit step state machine, resume capability, and idempotency.
 */
final class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300; // 5 minutos máximo para provisionar

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        public readonly string $slug,
    ) {}

    public function handle(
        ReserveTenantDomainAction $reserveDomain,
        SetupTenantCoreDataAction $setupCoreData,
        ProvisionInfrastructureAction $provisionInfra,
    ): void {
        Log::info("Iniciando ProvisionTenantJob asíncrono para tenant: {$this->tenant->id}");

        try {
            DB::transaction(function () use ($reserveDomain, $setupCoreData, $provisionInfra) {
                // Step 1: Subdomain / Domain Reservation
                $this->logStep('subdomain', function () use ($reserveDomain) {
                    $reserveDomain->execute($this->tenant, $this->slug);
                });

                // Step 2: Database Schema & Core Data
                $this->logStep('db_schema', function () use ($setupCoreData) {
                    $setupCoreData->execute($this->tenant);
                });

                // Step 3: Infrastructure (DNS, Cloud, etc)
                $this->logStep('infrastructure', function () use ($provisionInfra) {
                    $provisionInfra->execute($this->tenant);
                });

                // Step 4: Initial Owner User
                $this->logStep('admin_user', function () {
                    TenantProvisioned::dispatch($this->tenant, $this->adminEmail, 'Administrator', $this->adminPassword);
                });

                // Finalize: Active
                $this->tenant->update([
                    'status' => 'active',
                    'provisioned_at' => now(),
                ]);

                // Invalidate infrastructure caches
                Cache::forget('horizon_tenant_queues');

                activity('provisioning')
                    ->performedOn($this->tenant)
                    ->log('tenant_provisioned_successfully');
            });
        } catch (\Exception $e) {
            Log::error("Fallo asíncrono en la provisión del tenant {$this->slug}: ".$e->getMessage());

            $this->tenant->update([
                'status' => 'provisioning_failed',
            ]);

            throw $e;
        }
    }

    private function logStep(string $step, callable $callback): void
    {
        $log = ProvisioningLog::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'step' => $step,
            'status' => 'pending',
            'executed_at' => now(),
        ]);

        try {
            $callback();
            $log->update(['status' => 'completed']);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
