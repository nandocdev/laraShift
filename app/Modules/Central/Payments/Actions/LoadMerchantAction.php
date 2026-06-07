<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\DTOs\MerchantData;

final readonly class LoadMerchantAction
{
    public function __construct(
        private PaymentGateway $gateway,
    ) {}

    public function execute(string $apiKey): MerchantData
    {
        return $this->gateway->loadMerchant($apiKey);
    }
}
