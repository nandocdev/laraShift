<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;

it('exposes explicit capabilities per provider and method', function () {
    $clave = app(ClaveGateway::class);
    $dlocal = app(DlocalGateway::class);

    // Clave: redirect only, recurrence via scheduler.
    expect($clave->supports(BillingCapability::Checkout))->toBeTrue()
        ->and($clave->supports(BillingCapability::DirectPayment))->toBeFalse()
        ->and($clave->supports(BillingCapability::Subscriptions))->toBeFalse();

    // dLocal: redirect + direct card + MIT subscriptions on card, never cash.
    expect($dlocal->supports(BillingCapability::Checkout))->toBeTrue()
        ->and($dlocal->supports(BillingCapability::DirectPayment))->toBeFalse()
        ->and($dlocal->supports(BillingCapability::DirectPayment, PaymentMethodType::Card))->toBeTrue()
        ->and($dlocal->supports(BillingCapability::Subscriptions, PaymentMethodType::Card))->toBeTrue()
        ->and($dlocal->supports(BillingCapability::Subscriptions, PaymentMethodType::Cash))->toBeFalse()
        ->and($dlocal->supports(BillingCapability::Refunds))->toBeFalse();
});
