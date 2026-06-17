<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\DTOs\MerchantData;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\DTOs\PayoutData;
use App\Modules\Central\Payments\DTOs\PayoutResultData;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use App\Modules\Central\Payments\Exceptions\ClaveGatewayException; // We might want to rename this to a generic PaymentGatewayException later

use App\Modules\Central\Payments\Actions\GenerateDLocalSignature;
use App\Modules\Central\Payments\Exceptions\DlocalGatewayException;

final class DlocalGateway implements PaymentGateway {
    private string $baseUrl;
    private string $login;
    private string $transKey;
    private string $secretKey;
    private string $webhookSecret;

    public function __construct(
        private readonly GenerateDLocalSignature $generateSignature
    ) {
        $config = config('payments.dlocal');
        $this->login = (string) $config['login'];
        $this->transKey = (string) $config['trans_key'];
        $this->secretKey = (string) $config['secret_key'];
        $this->webhookSecret = (string) ($config['webhook_secret'] ?? '');

        $this->baseUrl = $config['environment'] === 'production'
            ? 'https://api.dlocal.com'
            : 'https://sandbox.dlocal.com';
    }

    public function identifier(): string {
        return 'dlocal';
    }

    public function listTransactions(string $apiKey, array $filters = []): array {
        $url = "{$this->baseUrl}/payments";
        $date = $this->getDlocalDate();

        try {
            $headers = $this->buildHeaders($date);
            $response = Http::withHeaders($headers)->get($url, $filters);

            if ($response->failed()) {
                Log::error("dLocal listTransactions failed", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("dLocal listTransactions failure: " . $e->getMessage());
            return [];
        }
    }

    public function loadMerchant(string $apiKey): MerchantData {
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

    public function buildCheckoutUrl(PaymentData $payment, string $apiKey): string {
        $url = "{$this->baseUrl}/payments";
        $date = $this->getDlocalDate();
        $idempotencyKey = $payment->resolvedSlug() . '_' . time();

        $domainResolver = app(\App\Modules\Shared\Contracts\TenantDomainResolverContract::class);
        $tenantDomain = $domainResolver->resolveDomain($payment->tenantId) 
            ?? $payment->tenantId . '.' . config('tenancy.central_domain');
            
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ":$port" : '';
        $baseUrl = "$scheme://$tenantDomain$portSuffix";

        $payload = [
            'amount' => (float) $payment->amount,
            'currency' => 'USD',
            'country' => $payment->customFieldValues['country'] ?? 'UY', // Default country if not provided
            'order_id' => $payment->resolvedSlug(),
            'success_url' => "$baseUrl/billing/success",
            'back_url' => "$baseUrl/billing/cancel",
            'notification_url' => route('payments.webhooks.dlocal', ['tenant' => $payment->tenantId]),
            'payer' => [
                'name' => $payment->customFieldValues['name'] ?? 'Customer',
                'email' => $payment->email,
                'document' => $payment->customFieldValues['document'] ?? 'NA',
            ],
            'payment_method_flow' => 'REDIRECT',
            'metadata' => array_merge($payment->customFieldValues, ['tenant_id' => $payment->tenantId]),
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->buildHeaders($date, $jsonPayload, $idempotencyKey);

        Log::info("dLocal: Creating payment session V2.1", ['order_id' => $payment->resolvedSlug()]);

        $response = Http::withHeaders($headers)
            ->withBody($jsonPayload, 'application/json')
            ->send('POST', $url);

        if ($response->failed()) {
            Log::error("dLocal API Error V2.1", [
                'status' => $response->status(), 
                'body' => $response->body(),
                'headers' => $headers
            ]);
            throw new DlocalGatewayException("dLocal payment initiation failed: " . ($response->json()['message'] ?? 'Unknown error'));
        }

        $data = $response->json();

        return $data['redirect_url'] ?? throw new DlocalGatewayException("No redirect URL returned by dLocal");
    }

    public function verifyWebhook(string $payload, string $signature, string $secret): bool {
        if (str_contains($signature, 'Signature: ')) {
            $signature = last(explode('Signature: ', $signature));
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(array $payload): PaymentResultData {
        $status = match ((int) ($payload['status'] ?? 0)) {
            200 => PaymentStatus::Approved,
            100 => PaymentStatus::Pending,
            300 => PaymentStatus::Declined,
            400 => PaymentStatus::Cancelled,
            700 => PaymentStatus::Refunded,
            default => PaymentStatus::Failed,
        };

        return new PaymentResultData(
            gatewayReference: (string) ($payload['id'] ?? ''),
            displayId: (string) ($payload['order_id'] ?? ''),
            status: $status,
            amount: (float) ($payload['amount'] ?? 0),
            gatewayCode: 'DLOCAL',
            authorizationCode: (string) ($payload['authorization_code'] ?? null),
            errorCode: (string) ($payload['status_code'] ?? null),
            errorMessage: $payload['status_detail'] ?? null,
            raw: $payload,
        );
    }

    public function processDirectPayment(PaymentData $payment, string $apiKey, ?string $token = null): PaymentResultData {
        $url = "{$this->baseUrl}/payments";
        $date = $this->getDlocalDate();
        $idempotencyKey = $payment->resolvedSlug() . '_' . time();

        $payload = [
            'amount' => (float) $payment->amount,
            'currency' => 'USD',
            'country' => $payment->customFieldValues['country'] ?? 'UY',
            'order_id' => $payment->resolvedSlug(),
            'payment_method_id' => 'CARD',
            'payment_method_flow' => 'DIRECT',
            'payer' => [
                'name' => $payment->customFieldValues['name'] ?? 'Customer',
                'email' => $payment->email,
                'document' => $payment->customFieldValues['document'] ?? 'NA',
            ],
            'card' => [
                'token' => $token,
            ],
            'metadata' => array_merge($payment->customFieldValues, ['tenant_id' => $payment->tenantId]),
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->buildHeaders($date, $jsonPayload, $idempotencyKey);

        Log::info("dLocal: Processing direct payment", ['order_id' => $payment->resolvedSlug()]);

        $response = Http::withHeaders($headers)
            ->withBody($jsonPayload, 'application/json')
            ->send('POST', $url);

        $data = $response->json();

        if ($response->failed()) {
            Log::error("dLocal Direct Payment Failed", ['status' => $response->status(), 'body' => $data]);
            return new PaymentResultData(
                gatewayReference: (string) ($data['id'] ?? ''),
                displayId: $payment->displayId,
                status: PaymentStatus::Failed,
                amount: (float) $payment->amount,
                gatewayCode: 'DLOCAL',
                errorCode: (string) ($data['status_code'] ?? null),
                errorMessage: $data['message'] ?? $data['status_detail'] ?? 'Payment failed',
                raw: $data,
            );
        }

        return $this->parseWebhookPayload($data);
    }

    public function submitPayout(PayoutData $payout): PayoutResultData {
        $url = "{$this->baseUrl}/v3/payouts";
        $date = $this->getDlocalDate();
        
        $payload = [
            'amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'country' => $payout->country,
            'payout_method_id' => $payout->method,
            'external_id' => $payout->externalId,
            'beneficiary' => $payout->beneficiary,
            'notification_url' => $payout->callbackUrl ?? route('payments.webhooks.dlocal_payout', ['tenant' => $payout->tenantId]),
        ];

        if ($payout->description) {
            $payload['description'] = $payout->description;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->buildHeaders($date, $jsonPayload);

        Log::info("dLocal: Submitting Payout V3", ['external_id' => $payout->externalId]);

        $response = Http::withHeaders($headers)
            ->withBody($jsonPayload, 'application/json')
            ->send('POST', $url);

        $data = $response->json();

        if ($response->failed()) {
            Log::error("dLocal Payout Submission Failed", ['status' => $response->status(), 'body' => $data]);
            return new PayoutResultData(
                id: (string) ($data['id'] ?? ''),
                status: 'REJECTED',
                amount: $payout->amount,
                currency: $payout->currency,
                statusDetail: $data['message'] ?? $data['status_detail'] ?? 'Payout submission failed',
                errorCode: (string) ($data['code'] ?? null),
                raw: $data
            );
        }

        return new PayoutResultData(
            id: (string) $data['id'],
            status: $data['status'], // PENDING, PAID, REJECTED
            amount: (float) $data['amount'],
            currency: $data['currency'],
            statusDetail: $data['status_detail'] ?? null,
            raw: $data
        );
    }

    public function getPayoutStatus(string $payoutId): PayoutResultData {
        $url = "{$this->baseUrl}/v3/payouts/{$payoutId}";
        $date = $this->getDlocalDate();
        
        $headers = $this->buildHeaders($date);

        $response = Http::withHeaders($headers)->get($url);
        $data = $response->json();

        if ($response->failed()) {
            Log::error("dLocal Payout Status Check Failed", ['status' => $response->status(), 'id' => $payoutId]);
            throw new DlocalGatewayException("Failed to retrieve payout status for ID: {$payoutId}");
        }

        return new PayoutResultData(
            id: (string) $data['id'],
            status: $data['status'],
            amount: (float) $data['amount'],
            currency: $data['currency'],
            statusDetail: $data['status_detail'] ?? null,
            raw: $data
        );
    }

    private function getDlocalDate(): string {
        return now()->format('Y-m-d\TH:i:s.v\Z');
    }

    private function buildHeaders(string $date, ?string $body = null, ?string $idempotencyKey = null): array {
        $signature = $this->generateSignature->execute(
            $this->login,
            $date,
            $this->secretKey,
            $body
        );

        $headers = [
            'X-Date' => $date,
            'X-Login' => $this->login,
            'X-Trans-Key' => $this->transKey,
            'Authorization' => $signature,
            'X-Version' => '2.1',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }
}
