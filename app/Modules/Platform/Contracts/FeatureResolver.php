<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface FeatureResolver
{
    public function execute(TenantContract $tenant, bool $forceRefresh = false): array;
}
