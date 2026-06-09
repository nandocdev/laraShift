<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Plan as MonolithPlan;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Registra el método de pago y crea la suscripción para un tenant recién provisionado usando Laravel Cashier.
 */
final readonly class RegisterPaymentMethodAction
{
    /**
     * Ejecuta el registro de método de pago y suscripción.
     *
     * @param Tenant $tenant          Tenant recién creado (ya persistido en DB)
     * @param string $paymentToken    Token/PaymentMethod ID generado por Stripe.js en el frontend
     * @param string $planSlug        Slug del plan seleccionado (ej: 'pro', 'enterprise')
     * @return Subscription           La suscripción creada
     * @throws \Exception             Si el gateway falla
     */
    public function execute(Tenant $tenant, string $paymentToken, string $planSlug): Subscription
    {
        try {
            // 1. Asegurar que el tenant sea un cliente de Stripe
            if (! $tenant->stripe_id) {
                $tenant->createAsStripeCustomer();
            }

            // 2. Resolver el precio de Stripe a partir del slug del monolito
            $plan = MonolithPlan::where('slug', $planSlug)->firstOrFail();
            $stripePriceId = $plan->features['stripe_id'] ?? null;

            if (! $stripePriceId) {
                throw new \InvalidArgumentException("Plan [{$planSlug}] has no Stripe Price ID configured.");
            }

            // 3. Crear suscripción vía Cashier
            /** @var Subscription $subscription */
            $subscription = $tenant->newSubscription('default', $stripePriceId)
                ->create($paymentToken);

            Log::info("Subscription created via Cashier for tenant: {$tenant->id}", [
                'plan' => $planSlug,
                'subscription_id' => $subscription->id
            ]);

            return $subscription;

        } catch (\Exception $e) {
            Log::error("Failed to register payment method or create subscription: " . $e->getMessage());
            throw $e;
        }
    }
}
