<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Services\CheckoutManager;
use App\Modules\Central\Payments\Services\CheckoutSession;

final readonly class InitiateCheckoutAction {
    public function __construct(
        private CheckoutManager $manager,
    ) {
    }

    public function execute(PaymentData $data, string $tenantId, string $apiKey): CheckoutSession {
        return $this->manager->initiate($data, $tenantId, $apiKey);
    }
}
