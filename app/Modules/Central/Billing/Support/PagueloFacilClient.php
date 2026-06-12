<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PagueloFacilClient {
    private string $cclw;
    private string $apiToken;
    private string $baseUrl;

    public function __construct() {
        $this->cclw = config('billing.paguelofacil.cclw');
        $this->apiToken = config('billing.paguelofacil.api_token');
        $this->baseUrl = config('billing.paguelofacil.base_url');
    }

    private function client(): PendingRequest {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => $this->apiToken,
            ])
            ->acceptJson();
    }

    /**
     * Generate a hosted payment link (Enlace de Pago).
     * Reference: LinkDeamon.cfm
     */
    public function generatePaymentLink(array $data): string
    {
        $payload = [
            'CCLW' => $this->cclw,
            'CMTN' => number_format((float) $data['amount'], 2, '.', ''),
            'CDSC' => substr($data['description'], 0, 150),
            'RETURN_URL' => bin2hex($data['return_url']),
            'PARM_1' => $data['tenant_id'],
            'PARM_2' => $data['plan_id'],
        ];

        // Custom fields if provided (JSON Hex encoded)
        if (!empty($data['custom_fields'])) {
            $payload['PF_CF'] = bin2hex(json_encode($data['custom_fields']));
        }

        \Log::info("PagueloFacil generatePaymentLink Request", ['payload' => $payload]);

        // LinkDeamon.cfm usually lives at the root or under secure/sandbox
        // Based on docs: https://secure.paguelofacil.com/LinkDeamon.cfm
        // We use the root of baseUrl
        $domain = parse_url($this->baseUrl, PHP_URL_HOST);
        $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME);
        $url = "{$scheme}://{$domain}/LinkDeamon.cfm";

        $response = Http::asForm()->post($url, $payload);

        \Log::info("PagueloFacil generatePaymentLink Response", [
            'status' => $response->status(),
            'body' => $response->json()
        ]);

        $responseData = $response->throw()->json();

        if (!($responseData['success'] ?? false)) {
            throw new \Exception($responseData['message'] ?? 'Failed to generate PagueloFacil payment link.');
        }

        return $responseData['data']['url'];
    }

    /**
     * Create a customer in PagueloFacil.
     */
    public function createCustomer(array $data): array
    {
        // Try multiple prefixes as PagueloFacil API is inconsistent between environments
        $prefixes = ['/subscriptions-api/v1', '/PFManagementServices/api/v1'];
        $lastException = null;

        foreach ($prefixes as $prefix) {
            try {
                $response = $this->client()->post($prefix . '/Customer', [
                    'firstName' => $data['first_name'],
                    'lastName' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? '',
                    'address' => $data['address'] ?? '',
                ]);

                $responseData = $response->json();

                if ($response->successful() && ($responseData['success'] ?? false) === true) {
                    return $responseData;
                }

                if ($response->status() !== 404 && ($responseData['message'] ?? '') !== 'Recurso no encontrado.') {
                     throw new \Exception($responseData['message'] ?? 'API Error');
                }
            } catch (\Exception $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new \Exception('Recurso no encontrado en ningún endpoint de PagueloFacil.');
    }

    /**
     * Create a subscription for a customer.
     */
    public function createSubscription(array $data): array
    {
        $payload = [
            'idPlan' => $data['plan_id'],
            'idCustomer' => $data['customer_id'],
            'startDate' => now()->format('Y-m-d\TH:i:s'),
            'period' => $data['period'] ?? 'mo', // mo, wk, yr
            'amount' => $data['amount'],
            'chargeContinue' => true,
            'applyPaymentsNow' => true,
            'requestPay' => [
                'cardInformation' => [
                    'cardNumber' => $data['card_number'],
                    'expMonth' => $data['exp_month'],
                    'expYear' => $data['exp_year'],
                    'cvv' => $data['cvv'],
                    'firstName' => $data['first_name'],
                    'lastName' => $data['last_name'],
                    'cardType' => $this->detectCardType($data['card_number']),
                ]
            ]
        ];

        \Log::info("PagueloFacil createSubscription Request", [
            'payload' => array_merge($payload, [
                'requestPay' => ['cardInformation' => ['cardNumber' => 'REDACTED', 'cvv' => 'REDACTED']]
            ])
        ]);

        $response = $this->client()->post('/subscriptions-api/v1/CustomerSubscriptions', $payload);
        $data = $response->json();

        \Log::info("PagueloFacil createSubscription Response", [
            'status' => $response->status(),
            'body' => $data
        ]);

        if ($response->successful() && ($data['success'] ?? false) === false) {
            throw new \Exception($data['message'] ?? 'PagueloFacil API Error without message');
        }

        return $response->throw()->json();
    }

    /**
     * Get details of a subscription.
     */
    public function getSubscription(string $subscriptionId): array
    {
        $response = $this->client()->get("/subscriptions-api/v1/CustomerSubscriptions/{$subscriptionId}");

        return $response->throw()->json();
    }

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        $response = $this->client()->post('/CancelSubscription', [
            'idSubscription' => $subscriptionId,
        ]);

        return $response->throw()->json();
    }

    /**
     * List merchant transactions.
     * Reference: /PFManagementServices/api/v1/MerchantTransactions
     */
    public function listTransactions(array $filters = []): array
    {
        // Resolve the prefix manually if baseUrl doesn't include it or ensure consistency
        $response = $this->client()->get('/MerchantTransactions', $filters);

        return $response->throw()->json();
    }



    private function detectCardType(string $number): string {
        $number = preg_replace('/\D/', '', $number);

        if (str_starts_with($number, '4')) return 'VISA';
        if (preg_match('/^5[1-5]/', $number)) return 'MASTERCARD';

        return 'VISA'; // Default fallback
    }
}
