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

final class ClaveGateway implements PaymentGateway
{
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
    ) {}

    // -------------------------------------------------------------------------
    // PaymentGateway contract
    // -------------------------------------------------------------------------

    public function loadMerchant(string $apiKey): MerchantData
    {
        $response = $this->post('/loadMerchantServices', [
            'CCLW'        => $apiKey,
            'serviceCode' => self::SERVICE_CODE,
        ], $apiKey);

        if (! ($response['success'] ?? false)) {
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

    public function buildCheckoutUrl(PaymentData $payment, string $apiKey): string
    {
        // We use the LinkDeamon.cfm endpoint which is proven to return a stable redirect URL
        // for the "Enlace de Pago" (Hosted Checkout) flow.
        
        $payload = [
            'CCLW'       => config('payments.clave.cclw', $apiKey),
            'CMTN'       => number_format((float) $payment->netAmount(), 2, '.', ''),
            'CDSC'       => substr($payment->description, 0, 150),
            'RETURN_URL' => bin2hex(config('app.url') . '/central/billing/paguelofacil/callback'),
            // We use PARM_1 for tenant_id and PARM_2 for internal reference if needed
            'PARM_1'     => $payment->customFieldValues['tenant_id'] ?? tenant('id'),
            'PARM_2'     => $payment->customFieldValues['plan_id'] ?? $payment->displayId,
        ];

        if (!empty($payment->customFieldValues)) {
            $payload['PF_CF'] = bin2hex(json_encode($payment->customFieldValues));
        }

        // Resolve root domain for LinkDeamon.cfm
        $baseUrl = $this->environment->apiBaseUrl();
        $domain  = parse_url($baseUrl, PHP_URL_HOST);
        $scheme  = parse_url($baseUrl, PHP_URL_SCHEME);
        $url     = "{$scheme}://{$domain}/LinkDeamon.cfm";

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

    public function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        // PagueLo Fácil signs webhooks with HMAC-SHA256
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(array $payload): PaymentResultData
    {
        return PaymentResultData::fromClavePayload($payload);
    }

    public function identifier(): string
    {
        return 'clave';
    }

    public function listTransactions(string $apiKey, array $filters = []): array
    {
        $response = $this->get('/MerchantTransactions', $filters, $apiKey);

        if (! ($response['success'] ?? false)) {
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
    private function post(string $path, array $body, ?string $apiKey = null): array
    {
        $url = rtrim($this->environment->apiBaseUrl(), '/') . $path;

        try {
            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => $apiKey ?? config('payments.clave.api_key'),
            ])
                ->timeout(15)
                ->retry(2, 500)
                ->post($url, $body);
        } catch (ConnectionException $e) {
            Log::error('ClaveGateway: connection failure', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new ClaveGatewayException('Clave gateway unreachable: ' . $e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            Log::error('ClaveGateway: HTTP error', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new ClaveGatewayException(
                sprintf('Clave API returned HTTP %d', $response->status()),
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @throws ClaveGatewayException
     */
    private function get(string $path, array $query, ?string $apiKey = null): array
    {
        $url = rtrim($this->environment->apiBaseUrl(), '/') . $path;

        try {
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => $apiKey ?? config('payments.clave.api_key'),
            ])
                ->timeout(15)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            Log::error('ClaveGateway: connection failure', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new ClaveGatewayException('Clave gateway unreachable: ' . $e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
             Log::error('ClaveGateway GET: HTTP error', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return ['success' => false];
        }

        return $response->json() ?? [];
    }
}
