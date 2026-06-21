<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Handlers;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\PaymentHandlerContract;
use App\Modules\Shared\Events\TenantSuspendedByDunning;
use Illuminate\Support\Facades\Log;

/**
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * CU-BILL-001: Procesar resultado de pago de membresía recurrente.
 *
 * Migrado desde FulfillSubscription listener + HandlePaymentFailure listener.
 * Centraliza toda la lógica post-pago para suscripciones en un solo handler.
 *
 * [LISTA DE RIESGOS - RUP]
 * 1. [Datos] plan_id ausente en metadata → se logea error y se aborta sin efecto lateral.
 * 2. [Seguridad] Tenant manipula metadata para escalar plan → plan_id validado contra BD.
 * 3. [Temporal] Webhook duplicado llama onApproved dos veces → Subscription::updateOrCreate es idempotente.
 */
final class SubscriptionPaymentHandler implements PaymentHandlerContract
{
    public function supports(): PaymentContext
    {
        return PaymentContext::Subscription;
    }

    public function onApproved(string $tenantId, string $displayId, float $amount, array $metadata): void
    {
        $planId = $metadata['plan_id'] ?? null;

        if (! $planId) {
            Log::error('SubscriptionPaymentHandler: plan_id missing in metadata', [
                'tenant_id' => $tenantId,
                'display_id' => $displayId,
            ]);

            return;
        }

        $tenant = Tenant::findOrFail($tenantId);
        $plan = Plan::findOrFail($planId);

        $subscription = Subscription::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider_subscription_id' => $metadata['gateway_reference'] ?? $displayId,
            ],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'gateway' => $metadata['gateway'] ?? 'clave',
                'current_period_end' => now()->addDays((int) ($plan->billing_cycle_days ?? 30)),
            ]
        );

        $tenant->update([
            'plan_id' => $plan->slug,
            'status' => 'active',
            'suspended_at' => null,
        ]);

        // Generate Invoice for the tenant
        Invoice::updateOrCreate(
            [
                'provider_invoice_id' => $metadata['gateway_reference'] ?? $displayId,
            ],
            [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'amount' => (int) ($amount * 100),
                'currency' => $metadata['currency'] ?? 'USD',
                'status' => 'paid',
                'issued_at' => now(),
            ]
        );

        Log::info('SubscriptionPaymentHandler: subscription fulfilled', [
            'tenant' => $tenantId,
            'plan' => $plan->slug,
            'display_id' => $displayId,
        ]);
    }

    public function onFailed(string $tenantId, string $displayId, string $reason, array $metadata): void
    {
        $attemptCount = (int) ($metadata['attempt_count'] ?? 1);

        if ($attemptCount >= 3) {
            $tenant = Tenant::find($tenantId);

            if ($tenant) {
                $tenant->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);

                TenantSuspendedByDunning::dispatch($tenantId, $displayId);

                activity('billing')
                    ->performedOn($tenant)
                    ->withProperties([
                        'display_id' => $displayId,
                        'attempts' => $attemptCount,
                        'reason' => $reason,
                    ])
                    ->log('tenant_suspended_by_dunning');
            }

            Log::alert('SubscriptionPaymentHandler: max attempts reached, tenant suspended', [
                'tenant' => $tenantId,
                'attempts' => $attemptCount,
            ]);
        } else {
            Log::info('SubscriptionPaymentHandler: payment attempt failed', [
                'tenant' => $tenantId,
                'attempt' => $attemptCount,
                'reason' => $reason,
            ]);
        }
    }
}
