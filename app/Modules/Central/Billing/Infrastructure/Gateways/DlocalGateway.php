<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Application\DTO\MerchantData;
use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Exceptions\RecurringBillingNotSupportedException;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Platform\Integrations\Dlocal\Client\DlocalHttpClient;
use App\Modules\Platform\Integrations\Dlocal\Contracts\PaymentGatewayContract;
use App\Modules\Platform\Integrations\Dlocal\DTOs\PayerData;
use App\Modules\Platform\Integrations\Dlocal\DTOs\PaymentRequestData;
use App\Modules\Platform\Integrations\Dlocal\Enums\PaymentMethodFlow;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DlocalGateway implements PaymentGateway
{
    public function __construct(
        private readonly PaymentGatewayContract $gateway,
        private readonly DlocalHttpClient $client,
    ) {}

    public function identifier(): string
    {
        return 'dlocal';
    }

    public function listTransactions(string $apiKey, array $filters = []): array
    {
        try {
            return $this->client->get('/payments');
        } catch (\Exception $e) {
            Log::error('dLocal listTransactions failure: '.$e->getMessage());

            return [];
        }
    }

    public function loadMerchant(string $apiKey): MerchantData
    {
        return new MerchantData(
            id: (string) config('dlocal.login', ''),
            slug: 'dlocal-merchant',
            name: 'dLocal Merchant',
            legalName: 'dLocal Merchant S.A.',
            dailyAmountLimit: 0,
            monthlyAmountLimit: 0,
            services: []
        );
    }

    public function buildCheckoutUrl(PaymentData $payment, string $apiKey): string
    {
        $isSubscription = ($payment->customFieldValues['type'] ?? '') === 'subscription';

        $requestData = new PaymentRequestData(
            orderId: $payment->resolvedSlug(),
            amountInCents: (int) round($payment->netAmount() * 100),
            currency: 'USD',
            country: (string) ($payment->customFieldValues['country'] ?? 'US'),
            payer: new PayerData(
                name: (string) ($payment->customFieldValues['name'] ?? 'Customer'),
                email: $payment->email,
                documentId: $payment->customFieldValues['document_id'] ?? null,
                userReference: $payment->tenantId,
            ),
            flow: PaymentMethodFlow::Redirect,
            description: $payment->description,
            save: $isSubscription ? true : null,
            storedCredentialType: $isSubscription ? 'SUBSCRIPTION' : null,
            storedCredentialUsage: $isSubscription ? 'FIRST' : null,
            notificationUrl: route('payments.webhooks.dlocal'),
            callbackUrl: route('central.billing.dlocal.callback'),
            metadata: array_merge($payment->customFieldValues, ['tenant_id' => $payment->tenantId]),
        );

        $response = $this->gateway->createPayment($requestData);

        PaymentReference::withoutEvents(function () use ($response, $payment): void {
            PaymentReference::firstOrCreate(
                ['external_reference' => $response->id],
                [
                    'order_id' => $payment->resolvedSlug(),
                    'context' => 'central',
                    'tenant_id' => $payment->tenantId,
                ],
            );
        });

        return $response->redirectUrl ?? throw new RuntimeException('No redirect URL returned by dLocal');
    }

    public function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(array $payload): PaymentResultData
    {
        $status = match ($payload['status'] ?? '') {
            'PAID', 'SUCCESS' => PaymentStatus::Approved,
            'REJECTED', 'CANCELLED' => PaymentStatus::Declined,
            'PENDING' => PaymentStatus::Pending,
            default => PaymentStatus::Failed,
        };

        return new PaymentResultData(
            gatewayReference: (string) ($payload['payment_id'] ?? $payload['id'] ?? ''),
            displayId: (string) ($payload['order_id'] ?? $payload['metadata']['displayId'] ?? ''),
            status: $status,
            amount: (float) ($payload['amount'] ?? 0),
            gatewayCode: 'DLOCAL',
            authorizationCode: (string) ($payload['authorization_code'] ?? null),
            errorCode: null,
            errorMessage: $payload['status_detail'] ?? null,
            raw: $payload,
        );
    }

    public function chargeSubscription(Subscription $subscription, int $amountInCents): PaymentResultData
    {
        $tenant = $subscription->tenant;

        if (! $tenant) {
            throw new RecurringBillingNotSupportedException('Recurring charge requires the subscription tenant.');
        }

        if (! $subscription->pm_card_id) {
            throw new RecurringBillingNotSupportedException(
                "Subscription {$subscription->id} has no saved payment method (pm_card_id)."
            );
        }

        $period = now()->format('Y-m');
        $orderId = "sub_{$subscription->id}_{$period}";

        $request = new PaymentRequestData(
            orderId: $orderId,
            amountInCents: $amountInCents,
            currency: 'USD',
            country: 'US',
            payer: new PayerData(
                name: $tenant->name,
                email: $tenant->email,
                userReference: $tenant->id,
            ),
            flow: PaymentMethodFlow::Direct,
            cardId: $subscription->pm_card_id,
            storedCredentialType: 'SUBSCRIPTION',
            storedCredentialUsage: 'USED',
            description: "Recurring subscription charge — {$subscription->id}",
            notificationUrl: route('payments.webhooks.dlocal'),
            metadata: [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'type' => 'recurring',
            ],
        );

        $response = $this->gateway->createPayment($request);

        PaymentReference::withoutEvents(function () use ($response, $tenant, $subscription): void {
            PaymentReference::firstOrCreate(
                ['external_reference' => $response->id],
                [
                    'order_id' => "sub_{$subscription->id}_".now()->format('Y-m'),
                    'context' => 'central',
                    'tenant_id' => $tenant->id,
                ],
            );
        });

        return new PaymentResultData(
            gatewayReference: $response->id,
            displayId: $orderId,
            status: match (true) {
                $response->status->isSuccessful() => PaymentStatus::Approved,
                $response->status->isRejected() => PaymentStatus::Declined,
                default => PaymentStatus::Pending,
            },
            amount: $response->amountInCents / 100,
            gatewayCode: 'DLOCAL',
            authorizationCode: null,
            errorCode: $response->status->isRejected() ? $response->statusDetail : null,
            errorMessage: $response->statusDetail,
            raw: ['payment_id' => $response->id, 'order_id' => $orderId, 'status' => $response->status->value],
        );
    }
}
