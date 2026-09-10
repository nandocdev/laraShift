<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentReference;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class ResolveWebhookTenant
{
    /**
     * Fase 0, regla exacta. Order: display_id → customer ref → fallback → fail.
     * Read-only: never writes, never creates.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     */
    public function execute(array $payload): string
    {
        $displayId = $payload['PARM_2'] ?? $payload['PARM_1'] ?? $payload['displayId'] ?? $payload['display_id'] ?? null;

        if (is_string($displayId) && $displayId !== '') {
            $owner = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

            if ($owner) {
                return (string) $owner;
            }
        }

        $customerId = $payload['customerId'] ?? $payload['provider_customer_id'] ?? null;

        if (is_string($customerId) && $customerId !== '') {
            $ref = PaymentReference::withoutGlobalScopes()
                ->where('external_reference', $customerId)
                ->where('context', PaymentReference::CONTEXT_CUSTOMER)
                ->first();

            if ($ref) {
                return (string) $ref->tenant_id;
            }

            $fallback = PaymentReference::withoutGlobalScopes()
                ->where('external_reference', $customerId)
                ->latest()
                ->first();

            if ($fallback) {
                Log::warning('billing.resolution_fallback', ['external_reference' => $customerId]);

                return (string) $fallback->tenant_id;
            }
        }

        // PARM_1 is our own echoed field (we sent the tenant id when building
        // the checkout URL). It is accepted only if it matches an existing
        // tenant row — never trusted blindly.
        $hint = $payload['PARM_1'] ?? $payload['tenant_id'] ?? null;

        if (is_string($hint) && $hint !== '') {
            $model = config('tenancy.tenant_model');

            if ($model::where('id', $hint)->exists()) {
                return $hint;
            }
        }

        throw new RuntimeException('Unresolvable webhook tenant.');
    }
}
