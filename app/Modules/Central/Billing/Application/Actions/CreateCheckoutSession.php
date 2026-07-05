<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class CreateCheckoutSession
{
    public function execute(Tenant $tenant, string $planId): string
    {
        return app(BillingManager::class)->createCheckoutSession($tenant, $planId);
    }
}
