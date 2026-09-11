<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface TenantBrandResolverContract
{
    /**
     * Display name of the active tenant brand.
     */
    public function name(): string;

    /**
     * Logo URL of the active tenant, or null when not configured.
     */
    public function logoUrl(): ?string;
}
