<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Actions\ChargeDirectAction;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Exceptions\RecurringBillingNotSupported;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Platform\Contracts\Billing\DirectPaymentData;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;

use function Pest\Laravel\assertDatabaseHas;

function dlocalDirectPayload(string $tenantId, array $overrides = []): DirectPaymentData
{
    return new DirectPaymentData(
        tenantId: $tenantId,
        gateway: 'dlocal',
        orderId: $overrides['orderId'] ?? 'DSP-DIRECT-1',
        amountCents: 2900,
        currency: 'USD',
        method: PaymentMethodType::Card,
        paymentToken: array_key_exists('paymentToken', $overrides) ? $overrides['paymentToken'] : 'tok_test_smartfields',
        subscriptionId: $overrides['subscriptionId'] ?? null,
    );
}

function fakeDlocalDirect(array $overrides = []): void
{
    Http::fake([
        '*/payments' => Http::response(array_merge([
            'id' => 'D-DIRECT-001',
            'order_id' => 'DSP-DIRECT-1',
            'status' => 'APPROVED',
            'card_id' => 'CARD-TEST-1',
        ], $overrides)),
    ]);
}

it('charges directly with a Smart Fields token and stores the saved card', function () {
    fakeDlocalDirect();
    $tenant = dlocalTestTenant('dlocal-direct');

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::Active,
        'gateway' => 'dlocal',
    ]);

    $ref = app(ChargeDirectAction::class)->execute(
        dlocalDirectPayload($tenant->id, ['subscriptionId' => $subscription->id])
    );

    expect($ref->status)->toBe('approved')
        ->and($ref->cardId)->toBe('CARD-TEST-1');

    assertDatabaseHas('payments', [
        'tenant_id' => $tenant->id,
        'display_id' => 'DSP-DIRECT-1',
        'status' => PaymentStatus::Approved,
        'gateway_reference' => 'D-DIRECT-001',
    ]);
    expect($subscription->fresh()->pm_card_id)->toBe('CARD-TEST-1');
});

it('rejects direct charges without a token', function () {
    $tenant = dlocalTestTenant('dlocal-notoken');

    expect(fn () => app(ChargeDirectAction::class)->execute(
        dlocalDirectPayload($tenant->id, ['paymentToken' => null])
    ))->toThrow(InvalidArgumentException::class);
});

it('charges recurring MIT on the saved card', function () {
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-MIT-001', 'status' => 'APPROVED']),
    ]);
    $tenant = dlocalTestTenant('dlocal-mit');

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::Active,
        'gateway' => 'dlocal',
        'pm_card_id' => 'CARD-TEST-1',
        'current_period_end' => now()->subDay(),
    ]);

    $ref = app(DlocalGateway::class)->chargeRecurring($tenant, $subscription->id, 2900);

    expect($ref->status)->toBe('approved')
        ->and($ref->providerPaymentId)->toBe('D-MIT-001');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/payments')
        && ($request['stored_credential_usage'] ?? null) === 'USED');
});

it('refuses MIT without a saved card', function () {
    $tenant = dlocalTestTenant('dlocal-nocard');

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::Active,
        'gateway' => 'dlocal',
    ]);

    expect(fn () => app(DlocalGateway::class)->chargeRecurring($tenant, $subscription->id, 2900))
        ->toThrow(RecurringBillingNotSupported::class);
});
