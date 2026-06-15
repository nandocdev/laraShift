<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Billing\Actions\RegisterPaymentMethodAction;
use App\Modules\Central\Infrastructure\Actions\ProvisionInfrastructureAction;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Shared\Events\TenantProvisioned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class CreateTenantAction {
    public function __construct(
        private ReserveTenantDomainAction $reserveDomain,
        private SetupTenantCoreDataAction $setupCoreData,
        private ProvisionInfrastructureAction $provisionInfra,
        private RegisterPaymentMethodAction $registerPaymentMethod,
    ) {
    }

    /**
     * Executes the atomic provisioning of a new tenant with Step-based tracking and Rollback.
     * 
     * [SIDE-EFFECTS]
     * - Records infrastructure steps in provisioning_logs.
     * - Triggers automatic cleanup on critical failure.
     */
    public function execute(CreateTenantData $data): Tenant {
        /** @var Tenant|null $tenant */
        $tenant = Tenant::where('slug', $data->slug)->first();

        if ($tenant && $tenant->status !== 'failed') {
            throw new \Exception("Tenant with slug {$data->slug} already exists and is not in a failed state.");
        }

        try {
            return DB::transaction(function () use ($data, &$tenant) {
                if ($tenant) {
                    // Reset failed tenant for retry
                    $tenant->update([
                        'name' => $data->name,
                        'email' => $data->email,
                        'plan_id' => $data->plan_id,
                        'status' => 'provisioning',
                    ]);

                    // Clean up partial logs to avoid confusion
                    $tenant->provisioningLogs()->delete();
                } else {
                    $tenant = Tenant::create([
                        'id' => Str::uuid()->toString(),
                        'slug' => $data->slug,
                        'name' => $data->name,
                        'email' => $data->email,
                        'plan_id' => $data->plan_id,
                        'status' => 'provisioning',
                    ]);
                }
                // Step 1: Subdomain / Domain Reservation
                $this->logStep($tenant, 'subdomain', function () use ($tenant, $data) {
                    $this->reserveDomain->execute($tenant, $data->slug);
                });

                // Step 2: Database Schema & Core Data
                $this->logStep($tenant, 'db_schema', function () use ($tenant) {
                    $this->setupCoreData->execute($tenant);
                });

                // Step 3: Infrastructure (DNS, Cloud, etc)
                $this->logStep($tenant, 'infrastructure', function () use ($tenant) {
                    $this->provisionInfra->execute($tenant);
                });

                // Step 4: Initial Admin User
                $this->logStep($tenant, 'admin_user', function () use ($tenant, $data) {
                    TenantProvisioned::dispatch($tenant, $data->email, 'Administrator', $data->password);
                });

                // Step 5: Billing Setup — only for paid plans
                $this->logStep($tenant, 'billing_setup', function () use ($tenant, $data) {
                    $plan = null;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('plans', 'slug')) {
                        $plan = Plan::where('slug', $data->plan_id)->first();
                    } else {
                        $plan = Plan::where('provider_plan_id', $data->plan_id)->first()
                            ?? Plan::where('name', $data->plan_id)->first()
                            ?? Plan::first();
                    }

                    if ($plan && $plan->price_monthly->isPositive() && $data->payment_token) {
                        // Register payment method + subscription via Cashier
                        $this->registerPaymentMethod->execute(
                            $tenant,
                            $data->payment_token,
                            $data->plan_id
                        );
                    }
                });

                // Finalize: Active
                $tenant->update([
                    'status' => 'active',
                    'provisioned_at' => now(),
                ]);

                // Invalidate infrastructure caches
                \Illuminate\Support\Facades\Cache::forget('horizon_tenant_queues');

                activity('provisioning')
                    ->performedOn($tenant)
                    ->log('tenant_provisioned_successfully');

                return $tenant;
            });
        } catch (\Exception $e) {
            if ($tenant) {
                $this->handleFailure($tenant, $e);
            }
            throw $e;
        }
    }

    private function logStep(Tenant $tenant, string $step, callable $callback): void {
        $log = ProvisioningLog::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'step' => $step,
            'status' => 'pending',
            'executed_at' => now(),
        ]);

        try {
            $callback();
            $log->update(['status' => 'completed']);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function handleFailure(Tenant $tenant, \Exception $exception): void {
        Log::error("Provisioning failed for tenant {$tenant->slug}: " . $exception->getMessage());

        // Compensation Logic (Rollback)
        DB::transaction(function () use ($tenant) {
            $tenant->update(['status' => 'failed']);

            // Clean up resources that might cause orphan state
            $tenant->domains()->delete();

            // Note: DB cleanup depends on config. 
            // In LaraShift, we might preserve the failed tenant record for support analysis,
            // but delete it if the user wants an atomic "nothing happened" experience.
            // For now, we move to 'failed' to block access.
        });

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['error' => $exception->getMessage()])
            ->log('tenant_provisioning_rolled_back');
    }
}
