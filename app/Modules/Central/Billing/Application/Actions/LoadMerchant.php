<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Application\DTO\MerchantData;
use App\Modules\Central\Billing\Infrastructure\Gateways\PaymentGateway;

final readonly class LoadMerchant
{
    public function __construct(
        private PaymentGateway $gateway,
    ) {}

    public function execute(string $apiKey): MerchantData
    {
        return $this->gateway->loadMerchant($apiKey);
    }
}
