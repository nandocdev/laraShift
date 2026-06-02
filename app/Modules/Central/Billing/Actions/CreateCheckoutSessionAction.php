<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class CreateCheckoutSessionAction
{
    public function execute(Tenant $tenant, string $planId): string
    {
        return app(BillingManager::class)->createCheckoutSession($tenant, $planId);
    }
}
