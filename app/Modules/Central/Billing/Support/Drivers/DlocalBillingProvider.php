<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support\Drivers;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Payments\Services\Gateways\DlocalGateway;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DlocalBillingProvider implements BillingProvider
{
    public function __construct(
        private readonly DlocalGateway $gateway
    ) {}

    public function createCheckoutSession(Tenant $tenant, string $planId): string
    {
        $plan = Plan::where('slug', $planId)->firstOrFail();

        $tenantDomain = $tenant->domains()->first()?->domain
            ?? $tenant->slug.'.'.config('tenancy.central_domain');
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ":$port" : '';
        $baseUrl = "$scheme://$tenantDomain$portSuffix";

        $country = strtoupper($tenant->country ?? 'UY');
        $isSandbox = config('payments.dlocal.environment') === 'sandbox';

        if ($isSandbox) {
            $gatewayCountry = 'UY';
            $gatewayCurrency = 'UYU';
        } else {
            $countryCurrencies = [
                'UY' => 'UYU',
                'AR' => 'ARS',
                'BR' => 'BRL',
                'MX' => 'MXN',
                'CO' => 'COP',
                'CL' => 'CLP',
                'PE' => 'PEN',
                'EC' => 'USD',
                'PA' => 'USD',
                'SV' => 'USD',
            ];
            $gatewayCountry = $country;
            $gatewayCurrency = $countryCurrencies[$country] ?? 'USD';
        }

        $exchangeRates = [
            'USD' => 1.0,
            'UYU' => 40.0,
            'ARS' => 900.0,
            'BRL' => 5.4,
            'MXN' => 18.0,
            'COP' => 4000.0,
            'CLP' => 930.0,
            'PEN' => 3.8,
        ];
        $rate = $exchangeRates[$gatewayCurrency] ?? 1.0;
        $usdAmount = (float) $plan->price_monthly->getAmount() / 100;
        $localAmount = round($usdAmount * $rate, 2);

        $payload = [
            'external_id' => 'sub_'.$tenant->id.'_'.time(),
            'currency' => $gatewayCurrency,
            'country' => $gatewayCountry,
            'type' => 'MERCHANT_SUBSCRIPTION',
            'description' => "Subscription to {$plan->name}",
            'payment_method_id' => 'CARD', // We can use credit card as default
            'payment_method_flow' => 'REDIRECT',
            'payer' => [
                'name' => $tenant->name,
                'document' => 'NA',
                'email' => $tenant->email,
            ],
            'subscription' => [
                'start_date' => now()->format('Y-m-d'),
                'frequency' => 'MONTHLY',
                'amount' => [
                    'type' => 'FIXED',
                    'value' => $localAmount,
                ],
            ],
            'notification_url' => route('payments.webhooks.dlocal', ['tenant' => $tenant->id]),
            'callback_url' => "$baseUrl/billing/success",
        ];

        try {
            $response = $this->gateway->createEnrollment($payload);

            return $response['redirect_url'] ?? throw new \Exception('Redirect URL missing in dLocal response');
        } catch (\Exception $e) {
            Log::error("dLocal createCheckoutSession failed: {$e->getMessage()}");
            throw $e;
        }
    }

    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
        try {
            $this->gateway->cancelEnrollment($subscriptionId);

            $subscription = $tenant->subscriptions()
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            if ($subscription) {
                if ($immediately) {
                    $subscription->update([
                        'status' => 'cancelled',
                        'ends_at' => now(),
                    ]);
                } else {
                    $subscription->update([
                        'ends_at' => $subscription->current_period_end ?? now()->addMonth(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to cancel dLocal subscription: {$e->getMessage()}");
            throw $e;
        }
    }

    public function syncSubscription(Tenant $tenant): void
    {
        $subscription = $tenant->subscriptions()->latest()->first();

        if ($subscription && $subscription->provider_subscription_id) {
            try {
                $data = $this->getSubscriptionData($tenant, $subscription->provider_subscription_id);

                if ($data) {
                    $subscription->update([
                        'status' => strtolower($data['status'] ?? $subscription->status),
                        'current_period_end' => isset($data['current_period_end'])
                            ? Carbon::parse($data['current_period_end'])
                            : $subscription->current_period_end,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync dLocal subscription for tenant {$tenant->id}: {$e->getMessage()}");
            }
        }
    }

    public function getSubscriptionData(Tenant $tenant, string $subscriptionId): ?array
    {
        try {
            $response = $this->gateway->getEnrollment($subscriptionId);

            // Map dLocal status (ACTIVE, PENDING, REJECTED, CANCELLED)
            return [
                'status' => $response['status'] ?? null,
                'current_period_end' => isset($response['subscription']['end_date'])
                    ? $response['subscription']['end_date']
                    : now()->addMonth()->format('Y-m-d'), // Fallback
            ];
        } catch (\Exception $e) {
            Log::error('dLocal getSubscriptionData Error: '.$e->getMessage());

            return null;
        }
    }

    public function getInvoices(Tenant $tenant): array
    {
        // Fetch all transactions from the gateway filtered by tenant_id
        $apiKey = config('payments.dlocal.login');

        return $this->gateway->listTransactions((string) $apiKey, [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function createTrialSubscription(Tenant $tenant, string $planId, ?string $paymentToken, bool $withCard): string
    {
        if (! $withCard) {
            return 'trial_'.Str::random(12);
        }

        $plan = Plan::where('slug', $planId)->firstOrFail();

        $tenantDomain = $tenant->domains()->first()?->domain
            ?? $tenant->slug.'.'.config('tenancy.central_domain');
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ":$port" : '';
        $baseUrl = "$scheme://$tenantDomain$portSuffix";

        $country = strtoupper($tenant->country ?? 'UY');
        $isSandbox = config('payments.dlocal.environment') === 'sandbox';

        if ($isSandbox) {
            $gatewayCountry = 'UY';
            $gatewayCurrency = 'UYU';
        } else {
            $countryCurrencies = [
                'UY' => 'UYU',
                'AR' => 'ARS',
                'BR' => 'BRL',
                'MX' => 'MXN',
                'CO' => 'COP',
                'CL' => 'CLP',
                'PE' => 'PEN',
                'EC' => 'USD',
                'PA' => 'USD',
                'SV' => 'USD',
            ];
            $gatewayCountry = $country;
            $gatewayCurrency = $countryCurrencies[$country] ?? 'USD';
        }

        $exchangeRates = [
            'USD' => 1.0,
            'UYU' => 40.0,
            'ARS' => 900.0,
            'BRL' => 5.4,
            'MXN' => 18.0,
            'COP' => 4000.0,
            'CLP' => 930.0,
            'PEN' => 3.8,
        ];
        $rate = $exchangeRates[$gatewayCurrency] ?? 1.0;
        $usdAmount = (float) $plan->price_monthly->getAmount() / 100;
        $localAmount = round($usdAmount * $rate, 2);

        $payload = [
            'external_id' => 'sub_'.$tenant->id.'_'.time(),
            'currency' => $gatewayCurrency,
            'country' => $gatewayCountry,
            'type' => 'MERCHANT_SUBSCRIPTION',
            'description' => "Subscription to {$plan->name} (Trial)",
            'payment_method_id' => 'CARD',
            'payment_method_flow' => 'DIRECT',
            'payer' => [
                'name' => $tenant->name,
                'document' => '12345678',
                'email' => $tenant->email,
            ],
            'card' => [
                'token' => $paymentToken,
            ],
            'subscription' => [
                'start_date' => now()->addDays(14)->format('Y-m-d'),
                'frequency' => 'MONTHLY',
                'amount' => [
                    'type' => 'FIXED',
                    'value' => $localAmount,
                ],
            ],
            'notification_url' => route('payments.webhooks.dlocal', ['tenant' => $tenant->id]),
            'callback_url' => "$baseUrl/billing/success",
        ];

        try {
            $response = $this->gateway->createEnrollment($payload);

            return $response['id'] ?? throw new \Exception('Enrollment ID missing in dLocal response');
        } catch (\Exception $e) {
            Log::error("dLocal createTrialSubscription failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
