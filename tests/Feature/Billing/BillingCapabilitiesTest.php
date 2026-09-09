<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Services\Billing;
use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\StripeBillingProvider;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingEventType;
use Illuminate\Support\Str;

it('exposes the honest capability matrix per gateway', function () {
    expect(app(ClaveGateway::class)->supports(BillingCapability::Checkout))->toBeTrue()
        ->and(app(ClaveGateway::class)->supports(BillingCapability::DirectPayment))->toBeFalse()
        ->and(app(ClaveGateway::class)->supports(BillingCapability::Subscriptions))->toBeFalse()
        ->and(app(ClaveGateway::class)->supports(BillingCapability::RecurringCharge))->toBeFalse()
        ->and(app(DlocalGateway::class)->supports(BillingCapability::Checkout))->toBeTrue()
        ->and(app(DlocalGateway::class)->supports(BillingCapability::DirectPayment))->toBeTrue()
        ->and(app(DlocalGateway::class)->supports(BillingCapability::RecurringCharge))->toBeTrue()
        ->and(app(DlocalGateway::class)->supports(BillingCapability::CustomerPortal))->toBeFalse()
        ->and(app(StripeBillingProvider::class)->supports(BillingCapability::Checkout))->toBeTrue()
        ->and(app(StripeBillingProvider::class)->supports(BillingCapability::Subscriptions))->toBeTrue()
        ->and(app(StripeBillingProvider::class)->supports(BillingCapability::DirectPayment))->toBeFalse();
});

it('normalizes clave webhooks to billing events', function () {
    $event = app(ClaveGateway::class)->normalize([
        'status' => 1,
        'codOper' => 'OP-1',
        'totalPay' => 29.99,
        'PARM_2' => 'INV-1',
    ]);

    expect($event->type)->toBe(BillingEventType::PaymentSucceeded)
        ->and($event->providerReference)->toBe('OP-1')
        ->and($event->displayId)->toBe('INV-1')
        ->and($event->amountCents)->toBe(2999);
});

it('normalizes dlocal webhooks to billing events', function () {
    $event = app(DlocalGateway::class)->normalize([
        'payment_id' => 'D-1',
        'order_id' => 'INV-2',
        'status' => 'PAID',
        'amount' => 10.5,
    ]);

    expect($event->type)->toBe(BillingEventType::PaymentSucceeded)
        ->and($event->amountCents)->toBe(1050);
});

it('normalizes stripe events to billing events', function () {
    $event = app(StripeBillingProvider::class)->normalize([
        'id' => 'evt_1',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => ['id' => 'in_1', 'amount_paid' => 2999]],
    ]);

    expect($event->type)->toBe(BillingEventType::PaymentSucceeded)
        ->and($event->providerReference)->toBe('evt_1')
        ->and($event->amountCents)->toBe(2999);
});

it('resolves the payment gateway from the tenant record', function () {
    $makeTenant = fn (string $gateway) => Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'gw-'.Str::random(6),
        'name' => 'GW Tenant',
        'email' => 'gw-'.Str::random(6).'@test.com',
        'plan_id' => 'free',
        'status' => 'active',
        'billing_gateway' => $gateway,
    ]);

    $manager = app(BillingManager::class);

    expect($manager->paymentGatewayForTenantId((string) $makeTenant('dlocal')->id))->toBeInstanceOf(DlocalGateway::class)
        ->and($manager->paymentGatewayForTenantId((string) $makeTenant('clave')->id))->toBeInstanceOf(ClaveGateway::class);
});

it('answers capability and active status through the Billing facade', function () {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'fac-'.Str::random(6),
        'name' => 'Facade Tenant',
        'email' => 'fac-'.Str::random(6).'@test.com',
        'plan_id' => 'free',
        'status' => 'active',
        'billing_gateway' => 'clave',
    ]);

    $scope = app(Billing::class)->for($tenant);

    expect($scope->supports(BillingCapability::Checkout))->toBeTrue()
        ->and($scope->supports(BillingCapability::Subscriptions))->toBeFalse()
        ->and($scope->active())->toBeFalse();
});
