<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PagueloFacilClient
{
    private string $cclw;
    private string $apiToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->cclw = config('billing.paguelofacil.cclw');
        $this->apiToken = config('billing.paguelofacil.api_token');
        $this->baseUrl = config('billing.paguelofacil.base_url');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => "{$this->cclw}|{$this->apiToken}",
            ])
            ->acceptJson();
    }

    /**
     * Create a customer in PagueloFacil.
     */
    public function createCustomer(array $data): array
    {
        $response = $this->client()->post('/Customer', [
            'firstName' => $data['first_name'],
            'lastName' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
        ]);

        return $response->throw()->json();
    }

    /**
     * Create a subscription for a customer.
     */
    public function createSubscription(array $data): array
    {
        $response = $this->client()->post('/CustomerSubscriptions', [
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
        ]);

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

    private function detectCardType(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);
        
        if (str_starts_with($number, '4')) return 'VISA';
        if (preg_match('/^5[1-5]/', $number)) return 'MASTERCARD';
        
        return 'VISA'; // Default fallback
    }
}
