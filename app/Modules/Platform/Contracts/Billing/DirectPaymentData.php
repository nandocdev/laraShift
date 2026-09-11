<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class DirectPaymentData extends Data
{
    public function __construct(
        public string $tenantId,
        public string $gateway,
        public string $orderId,
        public int $amountCents,
        public string $currency,
        public PaymentMethodType $method,
        public ?string $paymentToken = null,
        public ?string $providerCustomerId = null,
        public ?string $subscriptionId = null,
        /** Payer national ID (DNI/CPF/RUC). Required by dLocal for LatAm card payments. */
        public ?string $payerDocument = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
