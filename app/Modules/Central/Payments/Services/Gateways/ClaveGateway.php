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
        ]);

        if (! $response['success']) {
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
        // The JS SDK embeds an iframe pointing to the gateway's hosted payment page.
        // We construct that URL server-side instead of relying on the JS widget.
        $params = http_build_query([
            'CCLW'              => $apiKey,
            'slug'              => $payment->resolvedSlug(),
            'amount'            => $payment->amount,
            'taxAmount'         => $payment->taxAmount,
            'discount'          => $payment->discount,
            'description'       => $payment->description,
            'displayId'         => $payment->displayId,
            'email'             => $payment->email,
            'lang'              => $payment->lang,
            'txChannel'         => $payment->txChannel,
            'customFieldValues' => json_encode($payment->customFieldValues),
        ]);

        return rtrim($this->environment->checkoutBaseUrl(), '/') . '/pf/widget/clave?' . $params;
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

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * @throws ClaveGatewayException
     */
    private function post(string $path, array $body): array
    {
        $url = rtrim($this->environment->apiBaseUrl(), '/') . $path;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
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
}
