<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Infrastructure\Gateways\CheckoutManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\CheckoutSession;

final readonly class InitiateCheckout {
    public function __construct(
        private CheckoutManager $manager,
    ) {
    }

    public function execute(PaymentData $data, string $tenantId, string $apiKey): CheckoutSession {
        return $this->manager->initiate($data, $tenantId, $apiKey);
    }
}
