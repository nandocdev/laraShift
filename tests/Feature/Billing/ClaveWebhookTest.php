<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('rejects invalid signatures with 401 without touching the database', function () {
    $tenant = claveTestTenant('clave-401');

    $this->call('POST', route('payments.webhooks.clave'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CLAVE_SIGNATURE' => 'bogus',
    ], '{"status":1}')
        ->assertStatus(401);

    assertDatabaseCount('payment_gateway_events', 0);
    assertDatabaseCount('payment_webhooks', 0);
    assertDatabaseCount('payments', 0);
});

it('processes a duplicate webhook only once', function () {
    $tenant = claveTestTenant('clave-dup');

    $raw = claveRawPayload($tenant->id, 'DSP-DUP-1');
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CLAVE_SIGNATURE' => claveSign($raw)];

    $this->call('POST', route('payments.webhooks.clave'), [], [], [], $server, $raw)->assertNoContent();

    // Same delivery twice (gateway retry).
    $this->call('POST', route('payments.webhooks.clave'), [], [], [], $server, $raw)->assertNoContent();

    assertDatabaseCount('payment_gateway_events', 1);
    assertDatabaseHas('payments', [
        'tenant_id' => $tenant->id,
        'display_id' => 'DSP-DUP-1',
        'status' => PaymentStatus::Approved,
    ]);
    expect(Payment::where('tenant_id', $tenant->id)->where('display_id', 'DSP-DUP-1')->count())->toBe(1);
});

it('refuses to process a payment owned by another tenant', function () {
    $tenantA = claveTestTenant('clave-owner');
    $tenantB = claveTestTenant('clave-intruder');

    $payment = Payment::create([
        'tenant_id' => $tenantA->id,
        'slug' => 'pay_DSP-OWNED-1',
        'display_id' => 'DSP-OWNED-1',
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'gateway' => 'clave',
    ]);

    $raw = claveRawPayload($tenantB->id, 'DSP-OWNED-1');

    // Poisoned dispatch: job claims tenant B for tenant A's payment.
    $job = new ProcessPaymentWebhookJob(
        tenantId: $tenantB->id,
        gateway: 'clave',
        rawPayload: $raw,
        signature: claveSign($raw),
    );

    expect(fn () => $job->handle(app(ClaveGateway::class), app(DlocalGateway::class)))
        ->toThrow(RuntimeException::class, 'does not own the payment');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});
