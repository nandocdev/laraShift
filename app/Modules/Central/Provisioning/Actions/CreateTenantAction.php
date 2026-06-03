<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Billing\Actions\RegisterPaymentMethodAction;
use App\Modules\Central\Billing\Actions\SetupTenantPaymentProviderAction;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Shared\Events\TenantProvisioned;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class CreateTenantAction {
    /**
     * Executes the atomic provisioning of a new tenant with Step-based tracking and Rollback.
     * 
     * [SIDE-EFFECTS]
     * - Records infrastructure steps in provisioning_logs.
     * - Triggers automatic cleanup on critical failure.
     */
    public function execute(CreateTenantData $data): Tenant {
        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'slug' => $data->slug,
            'name' => $data->name,
            'email' => $data->email,
            'plan_id' => $data->plan_id,
            'status' => 'provisioning',
        ]);

        try {
            // Step 1: Subdomain / Domain Reservation
            $this->logStep($tenant, 'subdomain', function () use ($tenant, $data) {
                $domain = $data->slug . '.' . config('tenancy.central_domain', 'larashift.test');
                $tenant->domains()->create(['domain' => $domain]);
            });

            // Step 2: Database Schema & Core Data
            // Note: Stancl/Tenancy automatically creates DB on Tenant created if configured,
            // but here we track it as a conceptual step.
            $this->logStep($tenant, 'db_schema', function () {
                // If we had manual migration logic, it would go here.
            });

            // Step 3: Initial Admin User (Cross-module via Event)
            $this->logStep($tenant, 'admin_user', function () use ($tenant, $data) {
                TenantProvisioned::dispatch($tenant, $data->email, 'Administrator', $data->password);
            });

            // Step 4: Billing Setup (Plinth) — only for paid plans
            $this->logStep($tenant, 'billing_setup', function () use ($tenant, $data) {
                $plan = Plan::where('slug', $data->plan_id)->first();

                if ($plan && $plan->price_monthly > 0 && $data->payment_token) {
                    // Register payment method + subscription
                    app(RegisterPaymentMethodAction::class)->execute(
                        $tenant,
                        $data->payment_token,
                        $data->plan_id
                    );

                    // Copy platform gateway credentials to tenant
                    app(SetupTenantPaymentProviderAction::class)->execute($tenant);
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
        } catch (\Exception $e) {
            $this->handleFailure($tenant, $e);
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
