<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\DTOs\MerchantData;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Exceptions\ClaveGatewayException;
use App\Modules\Central\Payments\Exceptions\InvalidMerchantException;
use App\Modules\Central\Payments\Exceptions\ServiceNotFoundException;

final class ClaveGateway implements PaymentGateway {
    /**
     * Gateway codes accepted as Clave services.
     */
    private const CLAVE_GATEWAY_CODES = ['CLAVE', 'CROEM_CLAV'];

    /**
     * Service code used when loading merchant services from the API.
     */
    private const SERVICE_CODE = 'LK';

    public function __construct(
        private readonly ClaveEnvironment $environment,
    ) {
    }

    public function loadMerchant(string $apiKey): MerchantData {
        // loadMerchantServices is usually under HostedFields or root depending on the API version
        // We'll try management base first
        $response = $this->post('/loadMerchantServices', [
            'CCLW' => $apiKey,
            'serviceCode' => self::SERVICE_CODE,
        ], $apiKey);

        if (!($response['success'] ?? false)) {
            throw new InvalidMerchantException(
                $response['description'] ?? 'Invalid merchant response',
            );
        }

        $services = $response['services'] ?? [];

        if (empty($services)) {
            throw new ServiceNotFoundException('No services returned for merchant');
        }

        $claveServices = array_values(array_filter(
            $services,
            fn(array $s) => in_array($s['gatewayCode'] ?? '', self::CLAVE_GATEWAY_CODES, true),
        ));

        if (empty($claveServices)) {
            throw new ServiceNotFoundException(
                'No CLAVE service found for this merchant. Valid gateway codes: ' . implode(', ', self::CLAVE_GATEWAY_CODES),
            );
        }

        // First service entry also carries merchant-level fields
        $merchantFields = $claveServices[0];

        return MerchantData::fromApiResponse($merchantFields, $claveServices);
    }

    public function buildCheckoutUrl(PaymentData $payment, string $apiKey): string {
        // LinkDeamon.cfm lives at the root of the apiBaseUrl
        $url = rtrim($this->environment->apiBaseUrl(), '/') . '/LinkDeamon.cfm';

        $payload = [
            'CCLW' => config('payments.clave.cclw', $apiKey),
            'CMTN' => number_format((float) $payment->netAmount(), 2, '.', ''),
            'CDSC' => substr($payment->description, 0, 150),
            'RETURN_URL' => bin2hex(route('central.billing.paguelofacil.callback')),
            'PARM_1' => $payment->tenantId,
            'PARM_2' => $payment->customFieldValues['plan_id'] ?? $payment->displayId,
        ];

        if (!empty($payment->customFieldValues)) {
            // PagueloFacil expects PF_CF as a hex-encoded JSON array of objects:
            // [ {"id":"key", "nameOrLabel":"Label", "type":"hidden", "value":"val"} ]
            $customFields = [];
            foreach ($payment->customFieldValues as $key => $value) {
                $customFields[] = [
                    'id' => $key,
                    'nameOrLabel' => ucwords(str_replace(['_', '-'], ' ', (string) $key)),
                    'type' => 'hidden',
                    'value' => (string) $value,
                ];
            }
            $payload['PF_CF'] = bin2hex(json_encode($customFields));
        }

        Log::info("PagueloFacil: Requesting Enlace de Pago", ['url' => $url, 'payload' => $payload]);

        $response = Http::asForm()->timeout(15)->post($url, $payload);

        if ($response->failed()) {
            throw new ClaveGatewayException("Failed to connect to PagueloFacil LinkDeamon: " . $response->status());
        }

        $responseData = $response->json();

        if (!($responseData['success'] ?? false)) {
            throw new ClaveGatewayException($responseData['message'] ?? 'Failed to generate PagueloFacil payment link.');
        }

        return $responseData['data']['url'] ?? throw new ClaveGatewayException('No URL returned by PagueloFacil.');
    }

    public function verifyWebhook(string $payload, string $signature, string $secret): bool {
        // PagueLo Fácil signs webhooks with HMAC-SHA256 if configured, 
        // or uses a simple token match. Our implementation assumes HMAC.
        if (empty($signature)) {
            // Fallback for non-signed webhooks if needed, but security first
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(array $payload): PaymentResultData {
        return PaymentResultData::fromClavePayload($payload);
    }

    public function identifier(): string {
        return 'clave';
    }

    public function listTransactions(string $apiKey, array $filters = []): array {
        // MerchantTransactions uses the management base URL
        $response = $this->get('/MerchantTransactions', $filters, $apiKey);

        if (!($response['success'] ?? false)) {
            return [];
        }

        return $response['data'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * @throws ClaveGatewayException
     */
    private function post(string $path, array $body, ?string $apiKey = null): array {
        $url = rtrim($this->environment->managementBaseUrl(), '/') . $path;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $apiKey ?? config('payments.clave.api_key'),
            ])
                ->timeout(5)
                ->retry(1, 100)
                ->post($url, $body);
        } catch (ConnectionException $e) {
            Log::error('ClaveGateway: connection failure', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new ClaveGatewayException('Clave gateway unreachable: ' . $e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            // Fallback to HostedFields if management fails (some accounts are legacy)
            if ($response->status() === 404 && !str_contains($url, '/HostedFields')) {
                $url = rtrim($this->environment->apiBaseUrl(), '/') . '/HostedFields' . $path;
                return $this->postRetry($url, $body, $apiKey);
            }

            Log::error('ClaveGateway: HTTP error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ClaveGatewayException(
                sprintf('Clave API returned HTTP %d', $response->status()),
            );
        }

        return $response->json() ?? [];
    }

    private function postRetry(string $url, array $body, ?string $apiKey = null): array {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => $apiKey ?? config('payments.clave.api_key'),
        ])->post($url, $body);

        return $response->json() ?? [];
    }

    /**
     * @throws ClaveGatewayException
     */
    private function get(string $path, array $query, ?string $apiKey = null): array {
        $url = rtrim($this->environment->managementBaseUrl(), '/') . $path;

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $apiKey ?? config('payments.clave.api_key'),
            ])
                ->timeout(5)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            Log::error('ClaveGateway: connection failure', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new ClaveGatewayException('Clave gateway unreachable: ' . $e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            Log::error('ClaveGateway GET: HTTP error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false];
        }

        return $response->json() ?? [];
    }
}
