<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Tenant\Access\Application\Jobs\ExportTenantDataJob;

final readonly class ExportTenantData
{
    public function execute(string $userId): void
    {
        ExportTenantDataJob::dispatch(tenant('id'), $userId);
    }
}
