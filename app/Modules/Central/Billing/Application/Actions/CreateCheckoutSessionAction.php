<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\CheckoutProvider;
use App\Modules\Platform\Contracts\Billing\CheckoutSessionData;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class CreateCheckoutSessionAction
{
    public function __construct(private BillingManager $billing) {}

    /**
     * Idempotent checkout creation. The displayId is the idempotency key:
     * concurrent submits with the same key collapse to a single payments row.
     */
    public function execute(TenantContract $tenant, PlanRef $plan, string $displayId): CheckoutSessionData
    {
        $gateway = $this->billing->providerFor($tenant);

        if (! $gateway instanceof CheckoutProvider) {
            throw new RuntimeException('Billing provider does not support checkout.');
        }

        $tenantId = (string) $tenant->getId();

        $payment = DB::transaction(function () use ($tenantId, $plan, $displayId) {
            $existing = Payment::where('tenant_id', $tenantId)
                ->where('display_id', $displayId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                return Payment::create([
                    'tenant_id' => $tenantId,
                    'slug' => 'pay_'.$displayId,
                    'display_id' => $displayId,
                    'amount_cents' => $plan->amountCents,
                    'currency' => $plan->currency,
                    'status' => PaymentStatus::Pending,
                    'gateway' => 'clave',
                    'provider_metadata' => ['plan_slug' => $plan->slug],
                ]);
            } catch (QueryException $e) {
                // Race: another transaction inserted the row after our lock check.
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                Log::info('billing.checkout_race_recovered', ['tenant_id' => $tenantId, 'display_id' => $displayId]);

                return Payment::where('tenant_id', $tenantId)->where('display_id', $displayId)->firstOrFail();
            }
        });

        $checkoutUrl = $payment->provider_metadata['checkout_url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            $session = $gateway->createCheckout($tenant, $plan, $displayId);
            $checkoutUrl = $session->url;

            $payment->update(['provider_metadata' => array_merge(
                $payment->provider_metadata ?? [],
                ['checkout_url' => $checkoutUrl, 'plan_slug' => $plan->slug]
            )]);
        }

        PaymentAttempt::create([
            'tenant_id' => $tenantId,
            'payment_id' => $payment->id,
            'slug' => 'att_'.$displayId.'_'.now()->format('His'),
            'status' => 'initiated',
            'payload' => ['display_id' => $displayId, 'plan_slug' => $plan->slug],
        ]);

        return new CheckoutSessionData(id: $displayId, url: $checkoutUrl, provider: 'clave');
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            || str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
