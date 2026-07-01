<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

interface TenantDomainResolverContract
{
    /**
     * Resolves the primary domain for a given tenant ID.
     *
     * @param  string|int  $tenantId
     */
    public function resolveDomain($tenantId): ?string;
}
