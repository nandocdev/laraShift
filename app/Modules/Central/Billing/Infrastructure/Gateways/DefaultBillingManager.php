<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Domain\Exceptions\UnsupportedBillingGateway;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\BillingProvider;
use App\Modules\Platform\Contracts\TenantContract;

final readonly class DefaultBillingManager implements BillingManager
{
    public function __construct(private ClaveGateway $clave) {}

    public function providerFor(TenantContract $tenant): BillingProvider
    {
        return match ($tenant->getBillingGateway()) {
            'clave' => $this->clave,
            default => throw new UnsupportedBillingGateway(
                "Unsupported billing gateway: {$tenant->getBillingGateway()}"
            ),
        };
    }
}
