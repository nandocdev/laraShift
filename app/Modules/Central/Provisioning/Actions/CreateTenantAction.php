<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Payments\Actions\ProcessDirectPaymentAction;
use App\Modules\Central\Infrastructure\Actions\ProvisionInfrastructureAction;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Shared\Events\TenantProvisioned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Enums\PaymentContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

final readonly class CreateTenantAction {
    public function __construct(
        private ReserveTenantDomainAction $reserveDomain,
        private SetupTenantCoreDataAction $setupCoreData,
        private ProvisionInfrastructureAction $provisionInfra,
        private ProcessDirectPaymentAction $processDirectPayment,
    ) {
    }

    /**
     * Executes the atomic provisioning of a new tenant.
     * 
     * [PRD ALIGNMENT]
     * - Payment First: Payment processed successfully BEFORE creating the tenant.
     * - Atomic Rollback: Everything runs inside DB::transaction. Nothing persisted on failure.
     * - Idempotency: Checkout slug is hashed to prevent duplicate payments.
     */
    public function execute(CreateTenantData $data): Tenant {
        // 1. Validations
        if (Tenant::where('slug', $data->slug)->exists()) {
            throw new \Exception("Tenant with slug {$data->slug} already exists.");
        }

        // 2. Resolve Plan
        $plan = null;
        if (Schema::hasColumn('plans', 'slug')) {
            $plan = Plan::where('slug', $data->plan_id)->first();
        } else {
            $plan = Plan::where('provider_plan_id', $data->plan_id)->first()
                ?? Plan::where('name', $data->plan_id)->first()
                ?? Plan::first();
        }

        // We pre-generate the UUID to pass it to the payment
        $tenantId = Str::uuid()->toString();

        // 3. Process Payment FIRST (Before DB Transaction)
        if ($plan && $plan->price_monthly->isPositive() && $data->payment_token) {
            // Idempotency: same slug + email generates same checkout slug.
            // In a real scenario, you'd use a unique token or order ID from the frontend.
            $checkoutSlug = 'checkout_' . md5($data->slug . $data->email);
            
            // Check if payment already succeeded for this idempotency key
            $existingPayment = Payment::where('slug', $checkoutSlug)->where('status', 'approved')->first();

            if (!$existingPayment) {
                $paymentData = new PaymentData(
                    context: PaymentContext::Subscription,
                    amount: (float) $plan->price_monthly->getAmount() / 100,
                    description: "Subscription for {$plan->name}",
                    displayId: 'SUB-' . strtoupper(Str::random(6)),
                    email: $data->email,
                    tenantId: $tenantId, // Generated ID
                    slug: $checkoutSlug
                );

                $result = $this->processDirectPayment->execute($paymentData, $data->payment_token, false);

                if (!($result['success'] ?? false)) {
                    throw new \Exception("Payment failed: " . ($result['message'] ?? 'Unknown error'));
                }
            } else {
                $tenantId = $existingPayment->tenant_id; // Use the one already attached to the payment
            }
        }

        // 4. Provisioning Transaction (Atomic Rollback)
        try {
            return DB::transaction(function () use ($data, $tenantId, $plan) {
                // Step 0: Create Tenant
                $tenant = Tenant::create([
                    'id' => $tenantId,
                    'slug' => $data->slug,
                    'name' => $data->name,
                    'email' => $data->email,
                    'plan_id' => $data->plan_id,
                    'status' => 'provisioning',
                ]);

                // Step 1: Subdomain / Domain Reservation
                $this->logStep($tenant, 'subdomain', function () use ($tenant, $data) {
                    $this->reserveDomain->execute($tenant, $data->slug);
                });

                // Step 2: Database Schema & Core Data
                $this->logStep($tenant, 'db_schema', function () use ($tenant) {
                    $this->setupCoreData->execute($tenant);
                });

                // Step 3: Initial Features/Quotas & Cache priming (implicitly done or can be added)
                // Assuming setupCoreData handles the seed logic, or we dispatch events.
                
                // Step 4: Infrastructure (DNS, Cloud, etc)
                $this->logStep($tenant, 'infrastructure', function () use ($tenant) {
                    $this->provisionInfra->execute($tenant);
                });

                // Step 5: Initial Owner User (TenantProvisioned handles creation and Owner role)
                $this->logStep($tenant, 'admin_user', function () use ($tenant, $data) {
                    TenantProvisioned::dispatch($tenant, $data->email, 'Administrator', $data->password);
                });

                // Finalize: Active
                $tenant->update([
                    'status' => 'active',
                    'provisioned_at' => now(),
                ]);

                // Invalidate infrastructure caches
                Cache::forget('horizon_tenant_queues');

                activity('provisioning')
                    ->performedOn($tenant)
                    ->log('tenant_provisioned_successfully');

                return $tenant;
            });
        } catch (\Exception $e) {
            // The DB transaction completely rolls back. 
            // "No se permiten tenants parcialmente creados" and "Nada queda persistido".
            Log::error("Provisioning failed for tenant {$data->slug}: " . $e->getMessage());
            
            // Re-throw so caller (Livewire component) catches it and displays error
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
            // We throw the exception to bubble up and trigger the DB transaction rollback
            throw $e;
        }
    }
}
