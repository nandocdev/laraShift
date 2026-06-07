<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\DTOs\MerchantData;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use App\Modules\Central\Payments\Exceptions\ClaveGatewayException; // We might want to rename this to a generic PaymentGatewayException later

final class DlocalGateway implements PaymentGateway
{
    private string $baseUrl;
    private string $login;
    private string $transKey;
    private string $secretKey;

    public function __construct()
    {
        $config = config('payments.dlocal');
        $this->login = (string) $config['login'];
        $this->transKey = (string) $config['trans_key'];
        $this->secretKey = (string) $config['secret_key'];
        
        $this->baseUrl = $config['environment'] === 'production'
            ? 'https://api.dlocal.com'
            : 'https://sandbox.dlocal.com';
    }

    public function identifier(): string
    {
        return 'dlocal';
    }

    public function loadMerchant(string $apiKey): MerchantData
    {
        // dLocal doesn't usually have a 'loadMerchant' like PagueloFacil
        // We'll return a static/mocked structure if not strictly needed for the flow
        return new MerchantData(
            id: $this->login,
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
        $url = "{$this->baseUrl}/api_v1/payments";

        // Signature for dLocal (Simplified version for Go/Legacy)
        // Usually it involves Login + Amount + Currency + Secret
        // Check actual dLocal Go docs for exact signature if needed
        
        $payload = [
            'amount' => $payment->amount,
            'currency' => 'USD',
            'description' => $payment->description,
            'order_id' => $payment->resolvedSlug(),
            'success_url' => route('central.billing.success', ['tenant' => $payment->customFieldValues['tenant_id'] ?? 'default']),
            'back_url' => route('tenant.billing.plans'),
            'notification_url' => route('payments.webhooks.dlocal'),
            'payer' => [
                'name' => $payment->customFieldValues['name'] ?? 'Customer',
                'email' => $payment->email,
            ],
            // Pass metadata for fulfillment
            'metadata' => $payment->customFieldValues,
        ];

        Log::info("dLocal: Creating payment session", ['payload' => $payload]);

        $response = Http::withHeaders([
            'X-Login' => $this->login,
            'X-Trans-Key' => $this->transKey,
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) {
            Log::error("dLocal API Error", ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception("dLocal payment initiation failed.");
        }

        $data = $response->json();

        // dLocal Go usually returns 'redirect_url' or 'payment_url'
        return $data['redirect_url'] ?? $data['payment_url'] ?? throw new \Exception("No redirect URL returned by dLocal");
    }

    public function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        // dLocal Go webhooks verification
        // Usually a signature header: X-Signature
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(array $payload): PaymentResultData
    {
        // Map dLocal status to LaraShift status
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
}
