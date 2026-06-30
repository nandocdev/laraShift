<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Tests;

use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Exceptions\InvalidMerchantException;
use App\Modules\Central\Payments\Exceptions\ServiceNotFoundException;
use App\Modules\Central\Payments\Services\Gateways\ClaveEnvironment;
use App\Modules\Central\Payments\Services\Gateways\ClaveGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ClaveGatewayTest extends TestCase
{
    private ClaveGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new ClaveGateway(ClaveEnvironment::Sandbox);
    }

    // ── loadMerchant ─────────────────────────────────────────────────────────

    public function test_load_merchant_returns_merchant_data_on_success(): void
    {
        Http::fake([
            '*/loadMerchantServices' => Http::response($this->validMerchantResponse(), 200),
        ]);

        $merchant = $this->gateway->loadMerchant('test-api-key');

        $this->assertSame('M001', $merchant->id);
        $this->assertSame('my-merchant', $merchant->slug);
        $this->assertCount(1, $merchant->services);
        $this->assertSame('CLAVE', $merchant->services[0]->gatewayCode);
    }

    public function test_load_merchant_throws_when_success_is_false(): void
    {
        Http::fake([
            '*/loadMerchantServices' => Http::response(['success' => false, 'description' => 'Bad key'], 200),
        ]);

        $this->expectException(InvalidMerchantException::class);
        $this->gateway->loadMerchant('bad-key');
    }

    public function test_load_merchant_throws_when_no_clave_service(): void
    {
        $response = $this->validMerchantResponse();
        $response['services'] = [
            array_merge($response['services'][0], ['gatewayCode' => 'OTHER_GATEWAY']),
        ];

        Http::fake(['*/loadMerchantServices' => Http::response($response, 200)]);

        $this->expectException(ServiceNotFoundException::class);
        $this->gateway->loadMerchant('test-api-key');
    }

    // ── verifyWebhook ─────────────────────────────────────────────────────────

    public function test_webhook_verification_passes_with_valid_signature(): void
    {
        $payload = '{"txId":"123","status":"approved"}';
        $secret = 'my-webhook-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->gateway->verifyWebhook($payload, $signature, $secret));
    }

    public function test_webhook_verification_fails_with_tampered_payload(): void
    {
        $payload = '{"txId":"123","status":"approved"}';
        $tamperedPayload = '{"txId":"123","status":"declined"}';
        $secret = 'my-webhook-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertFalse($this->gateway->verifyWebhook($tamperedPayload, $signature, $secret));
    }

    // ── buildCheckoutUrl ──────────────────────────────────────────────────────

    public function test_checkout_url_contains_expected_params(): void
    {
        Http::fake([
            '*/LinkDeamon.cfm' => Http::response([
                'success' => true,
                'data' => ['url' => 'https://sandbox.paguelofacil.com/checkout?id=123'],
            ], 200),
        ]);

        $payment = new PaymentData(
            amount: 99.99,
            description: 'Test order',
            displayId: 'INV-001',
            email: 'user@example.com',
            tenantId: 'test-tenant',
        );

        $url = $this->gateway->buildCheckoutUrl($payment, 'test-key');

        $this->assertSame('https://sandbox.paguelofacil.com/checkout?id=123', $url);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validMerchantResponse(): array
    {
        return [
            'success' => true,
            'services' => [
                [
                    'merchant_idMerchant' => 'M001',
                    'merchant_slug' => 'my-merchant',
                    'merchant_merchantName' => 'My Company',
                    'merchant_legalName' => 'My Company SA',
                    'merchant_dailyAmountLimit' => 10000,
                    'merchant_monthlyAmountLimit' => 100000,
                    'idMerchantService' => 'SVC001',
                    'gatewayCode' => 'CLAVE',
                    'txLimit' => 5000,
                    'dailyAmountLimit' => 10000,
                    'monthlyAmountLimit' => 100000,
                ],
            ],
        ];
    }
}
