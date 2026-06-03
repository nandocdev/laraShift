<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\RegisterPaymentGatewayResolver;
use App\Modules\Central\Provisioning\Models\Tenant;
use Plinth\MultiTenantBilling\Billing\Models\Subscription;
use Plinth\MultiTenantBilling\Billing\Services\BillingService;
use Plinth\MultiTenantBilling\Payments\Models\Customer;
use Plinth\MultiTenantBilling\Payments\Models\PaymentMethod;

/**
 * Registra el método de pago y crea la suscripción para un tenant recién provisionado.
 *
 * Flujo:
 * 1. Crea un Customer en plinth_customers con los datos del tenant.
 * 2. Persiste el token (generado en frontend via Stripe.js) como PaymentMethod.
 * 3. Crea la suscripción en plinth_subscriptions vía BillingService.
 *
 * @see \App\Modules\Central\Billing\Support\RegisterPaymentGatewayResolver
 *
 * [RIESGOS]
 * - Si el gateway rechaza la creación de suscripción, el tenant queda sin billing.
 *   Mitigado: CreateTenantAction captura la excepción y ejecuta rollback completo.
 * - Token expirado si el usuario tarda mucho en el formulario (Stripe tokens expiran ~5 min).
 *   Mitigado: Stripe.js genera el token al momento del submit.
 */
final readonly class RegisterPaymentMethodAction
{
    public function __construct(
        private RegisterPaymentGatewayResolver $resolver
    ) {}

    /**
     * Ejecuta el registro de método de pago y suscripción.
     *
     * @param Tenant $tenant          Tenant recién creado (ya persistido en DB)
     * @param string $paymentToken    Token generado por Stripe.js en el frontend
     * @param string $planSlug        Slug del plan seleccionado (ej: 'pro', 'enterprise')
     * @return Subscription           La suscripción creada
     * @throws \Exception             Si el gateway falla
     */
    public function execute(Tenant $tenant, string $paymentToken, string $planSlug): Subscription
    {
        // 1. Crear Customer en Plinth
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name'      => $tenant->name,
            'email'     => $tenant->email,
        ]);

        // 2. Registrar método de pago con el token del frontend
        PaymentMethod::create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'type'        => 'CARD',
            'token'       => $paymentToken,
            'is_default'  => true,
        ]);

        // 3. Resolver el plan de Plinth a partir del slug del monolito
        $plinthPlan = \Plinth\MultiTenantBilling\Billing\Models\Plan::where(
            'provider_plan_id',
            $this->resolveProviderPlanId($planSlug)
        )->first();

        if (! $plinthPlan) {
            // Fallback: crear plan on-the-fly si no existe en plinth_plans
            $monolitPlan = \App\Modules\Central\Billing\Models\Plan::where('slug', $planSlug)->firstOrFail();

            $plinthPlan = \Plinth\MultiTenantBilling\Billing\Models\Plan::create([
                'provider_plan_id' => $monolitPlan->features['stripe_id'] ?? $planSlug,
                'name'             => $monolitPlan->name,
                'currency'         => 'usd',
                'amount'           => $monolitPlan->price_monthly / 100,
                'interval'         => 'MONTH',
                'interval_count'   => 1,
            ]);
        }

        // 4. Crear suscripción vía BillingService
        $billingProvider = $this->resolver->resolveBillingProvider();
        $billingService  = new BillingService($billingProvider);

        return $billingService->createSubscription(
            (int) 0, // tenant_id manejado internamente
            $plinthPlan,
            [
                'customer_id'    => $customer->provider_customer_id ?? $customer->id,
                'payment_method' => $paymentToken,
                'email'          => $tenant->email,
                'name'           => $tenant->name,
                'tenant_id'      => $tenant->id,
            ]
        );
    }

    /**
     * Resuelve el provider_plan_id a partir del slug del monolito.
     */
    private function resolveProviderPlanId(string $slug): string
    {
        $plan = \App\Modules\Central\Billing\Models\Plan::where('slug', $slug)->first();

        return $plan?->features['stripe_id'] ?? $slug;
    }
}
