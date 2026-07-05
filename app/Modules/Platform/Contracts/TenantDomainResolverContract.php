<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface TenantDomainResolverContract
{
    /**
     * Resolves the primary domain for a given tenant ID.
     *
     * @param string|int $tenantId
     * @return string|null
     */
    public function resolveDomain($tenantId): ?string;
}
