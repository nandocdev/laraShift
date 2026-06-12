<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Services;

use App\Modules\Central\Provisioning\Models\Domain;
use App\Modules\Shared\Contracts\TenantDomainResolverContract;

class TenantDomainResolver implements TenantDomainResolverContract
{
    public function resolveDomain($tenantId): ?string
    {
        return Domain::where('tenant_id', $tenantId)->first()?->domain;
    }
}
