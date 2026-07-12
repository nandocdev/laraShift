<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface TenantAware
{
    /**
     * Returns the tenant ID for context rehydration.
     * Used by RehydrateTenantContext middleware to set RLS + tenancy
     * before the job handle() executes.
     */
    public function tenantId(): string;
}
