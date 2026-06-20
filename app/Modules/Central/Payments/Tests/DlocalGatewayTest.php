<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use App\Modules\Central\Payments\Services\Gateways\DlocalGateway;
use App\Modules\Central\Payments\Actions\GenerateDLocalSignature;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PayoutData;
use App\Modules\Central\Payments\Exceptions\DlocalGatewayException;
use Tests\TestCase;

use App\Modules\Shared\Contracts\TenantDomainResolverContract;

final class DlocalGatewayTest extends TestCase {
    private DlocalGateway $gateway;
    private $domainResolver;

    protected function setUp(): void {
        parent::setUp();
        
        Config::set('payments.dlocal', [
            'login' => 'test-login',
            'trans_key' => 'test-trans-key',
            'secret_key' => 'test-secret-key',
            'environment' => 'sandbox',
        ]);

        $this->domainResolver = $this->mock(TenantDomainResolverContract::class);
        $this->domainResolver->shouldReceive('resolveDomain')->andReturn('tenant1.example.com');

        $this->gateway = new DlocalGateway(new GenerateDLocalSignature());
    }

    public function test_build_checkout_url_sends_correct_headers_and_payload(): void {
        Http::fake([
            'https://sandbox.dlocal.com/payments' => Http::response([
                'redirect_url' => 'https://checkout.dlocal.com/pay/123'
            ], 200)
        ]);

        $payment = new PaymentData(
            context: \App\Modules\Central\Payments\Enums\PaymentContext::Subscription,
            amount: 100.50,
            description: 'Subscription Plan',
            displayId: 'SUBS-123',
            email: 'customer@example.com',
            tenantId: '00000000-0000-0000-0000-000000000001',
            customFieldValues: [
                'country' => 'BR',
                'name' => 'John Doe',
                'document' => '123456789'
            ]
        );

        $url = $this->gateway->buildCheckoutUrl($payment, 'ignored-key');

        $this->assertSame('https://checkout.dlocal.com/pay/123', $url);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $request->hasHeader('X-Login', 'test-login') &&
                   $request->hasHeader('X-Trans-Key', 'test-trans-key') &&
                   $request->hasHeader('X-Version', '2.1') &&
                   str_contains($request->header('Authorization')[0], 'V2-HMAC-SHA256') &&
                   $data['amount'] === 100.5 &&
                   $data['currency'] === 'USD' &&
                   $data['country'] === 'BR' &&
                   $data['payer']['email'] === 'customer@example.com';
        });
    }

    public function test_build_checkout_url_throws_on_api_error(): void {
        Http::fake([
            'https://sandbox.dlocal.com/payments' => Http::response([
                'message' => 'Invalid currency'
            ], 400)
        ]);

        $payment = new PaymentData(
            context: \App\Modules\Central\Payments\Enums\PaymentContext::Subscription,
            amount: 100.50,
            description: 'Test',
            displayId: 'INV-1',
            email: 'test@example.com',
            tenantId: '00000000-0000-0000-0000-000000000001'
        );

        $this->expectException(DlocalGatewayException::class);
        $this->expectExceptionMessage('Invalid currency');

        $this->gateway->buildCheckoutUrl($payment, 'key');
    }

    public function test_verify_webhook_passes_with_valid_signature(): void {
        $payload = '{"id":"PAY123","status":200}';
        $secret = 'test-webhook-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->gateway->verifyWebhook($payload, $signature, $secret));
    }

    public function test_verify_webhook_with_v2_prefix_passes(): void {
        $payload = '{"id":"PAY123","status":200}';
        $secret = 'test-webhook-secret';
        $signatureOnly = hash_hmac('sha256', $payload, $secret);
        $signatureWithPrefix = "V2-HMAC-SHA256, Signature: $signatureOnly";

        $this->assertTrue($this->gateway->verifyWebhook($payload, $signatureWithPrefix, $secret));
    }

    public function test_parse_webhook_payload_maps_correct_status(): void {
        $payload = [
            'id' => 'DLOC-123',
            'order_id' => 'INV-001',
            'status' => 200,
            'amount' => 50.00,
            'status_detail' => 'Paid successfully'
        ];

        $result = $this->gateway->parseWebhookPayload($payload);

        $this->assertSame('DLOC-123', $result->gatewayReference);
        $this->assertTrue($result->status->isSuccessful());
        $this->assertSame(50.0, $result->amount);
    }

    public function test_process_direct_payment_sends_correct_payload(): void {
        Http::fake([
            'https://sandbox.dlocal.com/payments' => Http::response([
                'id' => 'DLOC-DIR-123',
                'status' => 200,
                'amount' => 100.0,
                'order_id' => 'INV-DIR'
            ], 200)
        ]);

        $payment = new PaymentData(
            context: \App\Modules\Central\Payments\Enums\PaymentContext::Subscription,
            amount: 100.0,
            description: 'Direct Test',
            displayId: 'INV-DIR',
            email: 'direct@example.com',
            tenantId: '00000000-0000-0000-0000-000000000001'
        );

        $result = $this->gateway->processDirectPayment($payment, 'key', 'tok_123');

        $this->assertTrue($result->status->isSuccessful());
        $this->assertSame('DLOC-DIR-123', $result->gatewayReference);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $data['payment_method_flow'] === 'DIRECT' &&
                   $data['card']['token'] === 'tok_123' &&
                   $data['payer']['email'] === 'direct@example.com';
        });
    }

    public function test_submit_payout_sends_correct_v3_payload(): void {
        Http::fake([
            'https://sandbox.dlocal.com/v3/payouts' => Http::response([
                'id' => 'PO_123',
                'amount' => 500.0,
                'currency' => 'USD',
                'status' => 'PENDING',
                'status_detail' => 'Waiting for approval'
            ], 200)
        ]);

        $payout = new PayoutData(
            amount: 500.0,
            currency: 'USD',
            country: 'UY',
            tenantId: '00000000-0000-0000-0000-000000000001',
            externalId: 'EXT-PO-1',
            method: 'BANK_TRANSFER',
            beneficiary: ['name' => 'Tenant One', 'bank' => 'BBVA']
        );

        $result = $this->gateway->submitPayout($payout);

        $this->assertSame('PO_123', $result->id);
        $this->assertTrue($result->isPending());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v3/payouts') &&
                   $request->method() === 'POST';
        });
    }

    public function test_get_payout_status_returns_data(): void {
        Http::fake([
            'https://sandbox.dlocal.com/v3/payouts/PO_123' => Http::response([
                'id' => 'PO_123',
                'amount' => 500.0,
                'currency' => 'USD',
                'status' => 'PAID'
            ], 200)
        ]);

        $result = $this->gateway->getPayoutStatus('PO_123');

        $this->assertSame('PO_123', $result->id);
        $this->assertTrue($result->isSuccessful());
    }
}
