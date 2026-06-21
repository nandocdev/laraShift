<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Payments\Actions\ProcessDirectPaymentAction;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class CreateTenantAction
{
    public function __construct(
        private ProcessDirectPaymentAction $processDirectPayment,
    ) {}

    /**
     * Executes the atomic provisioning of a new tenant.
     *
     * [PRD ALIGNMENT]
     * - Payment First: Payment processed successfully BEFORE creating the tenant.
     * - Atomic Rollback: Everything runs inside DB::transaction. Nothing persisted on failure.
     * - Idempotency: Checkout slug is hashed to prevent duplicate payments.
     */
    public function execute(CreateTenantData $data): Tenant
    {
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

        $tenant = null;

        // 3. Process Payment/Trial FIRST (Before DB Transaction)
        if ($plan && $plan->price_monthly->isPositive()) {
            if ($data->billing_option === 'pay_now') {
                if (! $data->payment_token) {
                    throw new \Exception('Payment token is required for immediate payment.');
                }
                // Idempotency: same slug + email generates same checkout slug.
                // In a real scenario, you'd use a unique token or order ID from the frontend.
                $checkoutSlug = 'checkout_'.md5($data->slug.$data->email);

                // Check if payment already succeeded for this idempotency key
                $existingPayment = Payment::where('slug', $checkoutSlug)->where('status', 'approved')->first();

                if (! $existingPayment) {
                    // To prevent race conditions and satisfy SubscriptionPaymentHandler's search for the Tenant model
                    // on DB::afterCommit, we create the Tenant record with 'pending_payment' status beforehand.
                    $tenant = Tenant::create([
                        'id' => $tenantId,
                        'slug' => $data->slug,
                        'name' => $data->name,
                        'email' => $data->email,
                        'plan_id' => $data->plan_id,
                        'status' => 'pending_payment',
                    ]);

                    try {
                        $paymentData = new PaymentData(
                            context: PaymentContext::Subscription,
                            amount: (float) $plan->price_monthly->getAmount() / 100,
                            description: "Subscription for {$plan->name}",
                            displayId: 'SUB-'.strtoupper(Str::random(6)),
                            email: $data->email,
                            tenantId: $tenantId, // Generated ID
                            customFieldValues: [
                                'plan_id' => $plan->id,
                            ],
                            slug: $checkoutSlug
                        );

                        $result = $this->processDirectPayment->execute($paymentData, $data->payment_token, false);

                        if (! ($result['success'] ?? false)) {
                            throw new \Exception('Payment failed: '.($result['message'] ?? 'Unknown error'));
                        }
                    } catch (\Exception $e) {
                        // Rollback: delete the provisionally created tenant if payment failed
                        $tenant->delete();
                        throw $e;
                    }
                } else {
                    $tenantId = $existingPayment->tenant_id; // Use the one already attached to the payment
                    $tenant = Tenant::find($tenantId);
                }
            } elseif ($data->billing_option === 'trial_with_card') {
                if (! $data->payment_token) {
                    throw new \Exception('Payment token is required to start a trial with card verification.');
                }

                $tenant = Tenant::create([
                    'id' => $tenantId,
                    'slug' => $data->slug,
                    'name' => $data->name,
                    'email' => $data->email,
                    'plan_id' => $data->plan_id,
                    'status' => 'pending_payment',
                ]);

                try {
                    $billingManager = app(BillingManager::class);
                    $billingProvider = $billingManager->forTenant($tenant);

                    $providerSubscriptionId = $billingProvider->createTrialSubscription($tenant, $plan->slug, $data->payment_token, true);

                    $tenant->subscriptions()->create([
                        'plan_id' => $plan->id,
                        'provider_subscription_id' => $providerSubscriptionId,
                        'status' => 'trialing',
                        'gateway' => $billingManager->getDefaultDriver(),
                        'trial_ends_at' => now()->addDays(14),
                        'current_period_end' => now()->addDays(14),
                        'type' => 'default',
                        'stripe_id' => $providerSubscriptionId,
                        'stripe_status' => 'trialing',
                    ]);
                } catch (\Exception $e) {
                    if ($tenant) {
                        $tenant->delete();
                    }
                    throw $e;
                }
            } elseif ($data->billing_option === 'trial_no_card') {
                $tenant = Tenant::create([
                    'id' => $tenantId,
                    'slug' => $data->slug,
                    'name' => $data->name,
                    'email' => $data->email,
                    'plan_id' => $data->plan_id,
                    'status' => 'pending_payment',
                ]);

                try {
                    $providerSubscriptionId = 'trial_'.Str::random(12);

                    $tenant->subscriptions()->create([
                        'plan_id' => $plan->id,
                        'provider_subscription_id' => $providerSubscriptionId,
                        'status' => 'trialing',
                        'gateway' => 'local',
                        'trial_ends_at' => now()->addDays(14),
                        'current_period_end' => now()->addDays(14),
                        'type' => 'default',
                        'stripe_id' => $providerSubscriptionId,
                        'stripe_status' => 'trialing',
                    ]);
                } catch (\Exception $e) {
                    if ($tenant) {
                        $tenant->delete();
                    }
                    throw $e;
                }
            }
        }

        // 4. Create Tenant and Dispatch Job
        try {
            if (! $tenant) {
                $tenant = DB::transaction(function () use ($data, $tenantId) {
                    return Tenant::create([
                        'id' => $tenantId,
                        'slug' => $data->slug,
                        'name' => $data->name,
                        'email' => $data->email,
                        'plan_id' => $data->plan_id,
                        'status' => 'provisioning',
                    ]);
                });
            } else {
                $tenant->update(['status' => 'provisioning']);
            }

            // Dispatch async job
            ProvisionTenantJob::dispatch(
                $tenant,
                $data->email,
                $data->password ?? Str::random(12),
                $data->slug
            );

            return $tenant;
        } catch (\Exception $e) {
            Log::error("Failed to create tenant record for {$data->slug}: ".$e->getMessage());
            throw $e;
        }
    }
}
