<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

function dlocalRawPayload(string $displayId): string
{
    $payload = json_decode(
        file_get_contents(__DIR__.'/../../Fixtures/Billing/dlocal_webhook_paid.json') ?: '{}',
        true
    );
    $payload['order_id'] = $displayId;

    return (string) json_encode($payload);
}

function dlocalSign(string $raw): string
{
    config()->set('dlocal.webhook_secret', 'test-dlocal-secret');

    return hash_hmac('sha256', $raw, 'test-dlocal-secret');
}

function dlocalServer(string $raw): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => dlocalSign($raw),
    ];
}

function dlocalPost(string $raw): TestResponse
{
    return test()->getTestCase()->call('POST', route('payments.webhooks.dlocal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => dlocalSign($raw),
    ], $raw);
}

it('rejects invalid dlocal signatures with 401 without touching the database', function () {
    $this->call('POST', route('payments.webhooks.dlocal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'bogus',
    ], '{"status":"PAID"}')
        ->assertStatus(401);

    assertDatabaseCount('payment_gateway_events', 0);
    assertDatabaseCount('payments', 0);
});

it('processes a dlocal webhook once through the shared pipeline', function () {
    $tenant = dlocalTestTenant('dlocal-wh');

    // Real flow: the checkout/direct charge always writes the payment row first.
    Payment::create([
        'tenant_id' => $tenant->id,
        'slug' => 'pay_DSP-DLWH-1',
        'display_id' => 'DSP-DLWH-1',
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'gateway' => 'dlocal',
    ]);

    $raw = dlocalRawPayload('DSP-DLWH-1');

    $this->call('POST', route('payments.webhooks.dlocal'), [], [], [], dlocalServer($raw), $raw)
        ->assertNoContent();

    $this->call('POST', route('payments.webhooks.dlocal'), [], [], [], dlocalServer($raw), $raw)
        ->assertNoContent(); // gateway retry: ack without reprocessing

    assertDatabaseCount('payment_gateway_events', 1);
    assertDatabaseHas('payments', [
        'tenant_id' => $tenant->id,
        'display_id' => 'DSP-DLWH-1',
        'status' => PaymentStatus::Approved,
        'gateway' => 'dlocal',
    ]);
    expect(Payment::where('tenant_id', $tenant->id)->count())->toBe(1);
});
