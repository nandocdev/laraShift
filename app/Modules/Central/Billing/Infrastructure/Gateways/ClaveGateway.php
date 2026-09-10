<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Domain\Enums\BillingEventType;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Exceptions\WebhookVerificationFailed;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingEventData;
use App\Modules\Platform\Contracts\Billing\BillingProvider;
use App\Modules\Platform\Contracts\Billing\CheckoutProvider;
use App\Modules\Platform\Contracts\Billing\CheckoutSessionData;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use App\Modules\Platform\Contracts\Billing\WebhookProvider;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class ClaveGateway implements BillingProvider, CheckoutProvider, WebhookProvider
{
    public function __construct(private ClaveEnvironment $environment) {}

    public function supports(BillingCapability $capability, ?PaymentMethodType $forMethod = null): bool
    {
        // Clave/PagueloFácil is redirect-checkout only. Recurrence happens
        // through scheduler-generated checkouts (§9), never gateway-side.
        return $capability === BillingCapability::Checkout;
    }

    public function createCheckout(TenantContract $tenant, PlanRef $plan, string $displayId): CheckoutSessionData
    {
        return new CheckoutSessionData(
            id: $displayId,
            url: $this->buildCheckoutUrl($tenant, $plan, $displayId),
            provider: 'clave',
        );
    }

    /**
     * Build the hosted checkout (Enlace de Pago) URL via LinkDeamon.cfm.
     * Pure HTTP: writes nothing, creates no records.
     */
    public function buildCheckoutUrl(TenantContract $tenant, PlanRef $plan, string $displayId): string
    {
        $url = rtrim($this->environment->apiBaseUrl(), '/').'/LinkDeamon.cfm';

        $payload = [
            'CCLW' => config('clave.merchant_id'),
            'CMTN' => number_format($plan->amountCents / 100, 2, '.', ''),
            'CDSC' => substr("Plan {$plan->slug} — {$tenant->getName()}", 0, 150),
            'RETURN_URL' => bin2hex(route('payments.clave.callback')),
            'PARM_1' => (string) $tenant->getId(),
            'PARM_2' => $displayId,
            'PF_CF' => bin2hex((string) json_encode([
                ['id' => 'tenant_id', 'nameOrLabel' => 'Tenant Id', 'type' => 'hidden', 'value' => (string) $tenant->getId()],
                ['id' => 'plan_slug', 'nameOrLabel' => 'Plan Slug', 'type' => 'hidden', 'value' => $plan->slug],
            ])),
        ];

        try {
            $response = Http::asForm()->timeout(15)->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('ClaveGateway: connection failure', ['url' => $url, 'error' => $e->getMessage()]);

            throw new RuntimeException('Clave gateway unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            Log::error('ClaveGateway: HTTP error', ['url' => $url, 'status' => $response->status()]);

            throw new RuntimeException(sprintf('Clave API returned HTTP %d', $response->status()));
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            throw new RuntimeException($data['message'] ?? 'Failed to generate PagueloFacil payment link.');
        }

        return $data['data']['url'] ?? throw new RuntimeException('No URL returned by PagueloFacil.');
    }

    public function verify(string $rawPayload, string $signature): bool
    {
        $secret = (string) config('clave.webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawPayload, $secret), $signature);
    }

    /**
     * @throws WebhookVerificationFailed
     */
    public function verifyOrFail(string $rawPayload, string $signature): void
    {
        if (! $this->verify($rawPayload, $signature)) {
            throw new WebhookVerificationFailed('Invalid Clave webhook signature.');
        }
    }

    public function normalize(array $payload): BillingEventData
    {
        // Webhook uses 'status' (1 approved, 0 declined);
        // browser redirect uses 'Estado' ('Aprobada', 'Denegada').
        $status = match (true) {
            ($payload['status'] ?? null) === 1 => PaymentStatus::Approved,
            ($payload['status'] ?? null) === 0 => PaymentStatus::Declined,
            ($payload['Estado'] ?? '') === 'Aprobada' => PaymentStatus::Approved,
            ($payload['Estado'] ?? '') === 'Denegada' => PaymentStatus::Declined,
            default => PaymentStatus::Pending,
        };

        $type = match ($status) {
            PaymentStatus::Approved => BillingEventType::PaymentSucceeded->value,
            PaymentStatus::Declined => BillingEventType::PaymentFailed->value,
            default => BillingEventType::CheckoutCompleted->value,
        };

        return new BillingEventData(
            type: $type,
            gateway: 'clave',
            gatewayEventId: (string) ($payload['codOper'] ?? $payload['Oper'] ?? $payload['transactionId'] ?? ''),
            displayId: (string) ($payload['PARM_2'] ?? $payload['PARM_1'] ?? $payload['displayId'] ?? ''),
            providerCustomerId: isset($payload['customerId']) ? (string) $payload['customerId'] : null,
            amountCents: (int) round((float) ($payload['totalPay'] ?? $payload['TotalPagado'] ?? $payload['amount'] ?? 0) * 100),
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            raw: $payload,
        );
    }
}
