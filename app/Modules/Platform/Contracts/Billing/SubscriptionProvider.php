<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface SubscriptionProvider
{
    public function createSubscription(TenantContract $tenant, PlanRef $plan): ProviderSubscriptionRef;

    public function changePlan(TenantContract $tenant, string $providerSubscriptionId, PlanRef $plan): ProviderSubscriptionRef;

    public function cancel(TenantContract $tenant, string $providerSubscriptionId, bool $immediately = false): void;

    /**
     * Cobra una suscripción MIT-based (dLocal tarjeta). Clave no lo implementa
     * (sin recurrencia: LogicException); Stripe lo ignora (reloj propio, fase 2).
     */
    public function chargeRecurring(TenantContract $tenant, string $providerSubscriptionId, int $amountCents): ProviderPaymentRef;
}
